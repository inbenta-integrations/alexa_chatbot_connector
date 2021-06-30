<?php

namespace Inbenta\AlexaConnector\ExternalAPI;

class AlexaAPIClient
{
    /**
     * Create the external id
     */
    public static function buildExternalIdFromRequest()
    {
        $request = json_decode(file_get_contents('php://input'));

        if (!$request) {
            $request = (object)$_GET;
        }
        return isset($request->session->user) ? 'alexa-' . str_replace(".", "-", $request->session->user->userId ): null;
    }

    /**
     * Overwritten, not necessary with Alexa
     */
    public function showBotTyping($show = true)
    {
        return true;
    }
}
