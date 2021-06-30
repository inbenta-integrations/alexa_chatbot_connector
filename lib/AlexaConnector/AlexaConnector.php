<?php

namespace Inbenta\AlexaConnector;

use \Exception;
use Inbenta\ChatbotConnector\ChatbotConnector;
use Inbenta\ChatbotConnector\ChatbotAPI\ChatbotAPIClient;
use Inbenta\ChatbotConnector\Utils\SessionManager;
use Inbenta\AlexaConnector\ExternalAPI\AlexaAPIClient;
use Inbenta\AlexaConnector\ExternalDigester\AlexaDigester;


class AlexaConnector extends ChatbotConnector
{
    private $messages = [];

    public function __construct($appPath)
    {
        // Initialize and configure specific components for Alexa
        try {
            parent::__construct($appPath);

            // Initialize base components
            $request = file_get_contents('php://input');

            $externalId = $this->getExternalIdFromRequest();

            $conversationConf = array(
                'configuration' => $this->conf->get('conversation.default'),
                'userType'      => $this->conf->get('conversation.user_type'),
                'environment'   => $this->environment,
                'source'        => $this->conf->get('conversation.source')
            );

            $this->session = new SessionManager($externalId);

            //Validity check (recommended by Amazon)
            $this->validityCheck($request);

            $this->botClient = new ChatbotAPIClient(
                $this->conf->get('api.key'),
                $this->conf->get('api.secret'),
                $this->session,
                $conversationConf
            );

            $externalClient = new AlexaAPIClient(
                $this->conf->get('alexa.id'),
                $request
            ); // Instance Alexa client

            // Instance Alexa digester
            $externalDigester = new AlexaDigester(
                $this->lang,
                $this->conf->get('conversation.digester'),
                $this->session
            );
            $this->initComponents($externalClient, null, $externalDigester);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
            die();
        }
    }

    /**
     * Get the external id from request
     *
     * @return String 
     */
    protected function getExternalIdFromRequest()
    {
        // Try to get user_id from a Alexa message request
        $externalId = AlexaAPIClient::buildExternalIdFromRequest();
        if (is_null($externalId)) {
            session_write_close();
            throw new Exception('Invalid request!');
        }
        return $externalId;
    }

    /**
     * Validity check (as recommended by Amazon)
     */
    protected function validityCheck($input)
    {
        $post = json_decode($input);

        date_default_timezone_set('UTC');

        if ($this->conf->get('alexa.id') != $post->session->application->applicationId) {
            throw new Exception('Invalid request! (Alexa Skill ID does not match)');
        }

        if ($post->request->timestamp <= date('Y-m-d\TH:i:s\Z', time() - 150)) {
            throw new Exception('Invalid request! (timestamp is too old)');
        }
    }

    public function handleRequest()
    {
        try {

            $request = file_get_contents('php://input');

            // Translate the request into a ChatbotAPI request
            $externalRequest = $this->digester->digestToApi($request);

            if (!$externalRequest) {
                return [
                    "response" => [
                        "outputSpeech" => [
                            "type" => "PlainText",
                            "text" => "Goodbye"
                        ],
                        "shouldEndSession" => true
                    ]
                ];
            }

            // Check if it's needed to perform any action other than a standard user-bot interaction
            $nonBotResponse = $this->handleNonBotActions($externalRequest);
            if (!is_null($nonBotResponse)) {
                return $nonBotResponse;
            }

            // Handle standard bot actions
            $this->handleBotActions([$externalRequest]);
            // Send all messages
            return $this->sendMessages();
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    protected function handleNonBotActions($digestedRequest)
    {
        // If user answered to an ask-to-escalate question, handle it
        if ($this->session->get('askingForEscalation', false)) {
            return $this->handleEscalation($digestedRequest);
        }
        return null;
    }

    /**
     * Print the message that Alexa can process
     */
    public function sendMessages()
    {
        if (empty(trim($this->messages["text"]))) {
            $this->messages["text"] = $this->lang->translate('empty_response');
        }
        return [
            "response" => [
                "outputSpeech" => [
                    "type" => "PlainText",
                    "text" => $this->messages["text"]
                ],
                "shouldEndSession" => $this->messages["shouldEndSession"]
            ]
        ];
    }

    protected function sendMessagesToExternal($botResponse)
    {
        // Digest the bot response into the external service format
        $this->messages = $this->digester->digestFromApi($botResponse, $this->session->get('lastUserQuestion'));
    }

    /**
     * Overwritten
     */
    protected function handleEscalation($userAnswer = null)
    {
        $this->messages["text"] .=  " " . $this->lang->translate('no_escalation');
        return $this->sendMessages();
    }

}
