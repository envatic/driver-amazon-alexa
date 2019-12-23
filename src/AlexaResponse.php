<?php

namespace BotMan\Drivers\AmazonAlexa;

use Alexa\Response\Response;

class AlexaResponse extends Response
{

    const TYPE_PLAIN_TEXT = 'PlainText';

    const TYPE_SSML = 'SSML';

    public function respondText($text)
    {
        // $this->outputSpeech = new AlexaOutputSpeech;
        // $this->outputSpeech->type = self::TYPE_PLAIN_TEXT;
        // $this->outputSpeech->text = $text;
        $this->respond($text);

        return $this;
    }

    public function responseSSML($ssml)
    {
        return $this->respondSsml($ssml);
    }

    public function respondSsml($ssml)
    {
        $this->outputSpeech = new AlexaOutputSpeech;
        $this->outputSpeech->type = self::TYPE_SSML;
        $this->outputSpeech->ssml = $ssml;

        return $this;
    }

}