<?php

namespace BotMan\Drivers\AmazonAlexa;

use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use Techworker\Ssml\ContainerElement;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Drivers\Events\GenericEvent;
use Symfony\Component\HttpFoundation\Response;
use BotMan\Drivers\AmazonAlexa\Extensions\Card;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;

class AmazonAlexaDriver extends HttpDriver
{
    const DRIVER_NAME = 'AmazonAlexa';
    const LAUNCH_REQUEST = 'LaunchRequest';
    const SESSION_ENDED_REQUEST = 'SessionEndedRequest';

    protected $messages = [];

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = collect();
        $this->event = collect();
        $this->config = collect();

        $rawRequest = $request->getContent();

        if (empty($rawRequest)) {
            // Probably a package discovery
            return;
        }
        
        $this->payload = Collection::make((array) json_decode($rawRequest, true));
        $this->event = Collection::make((array) $this->payload->get('request'));
        $this->config = Collection::make($this->config->get('amazon-alexa', []));
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return \BotMan\BotMan\Users\User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User($matchingMessage->getSender());
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        if (! $this->event->has('requestId') || ! $this->event->has('type')) {
            return false;
        }
        /* This does the validation of the request, but we don't use the result */
        $alexa = new \Alexa\Request\Request(request()->getContent());
        $alexaRequest = $alexa->fromData();

        // If we get here then we're correct and validated
        return true;
    }

    /**
     * @param  IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $intent = $this->event->get('intent');
            $session = $this->payload->get('session');

            $message = new IncomingMessage($intent['name'], $session['user']['userId'], $session['sessionId'], $this->payload);
            if (! is_null($intent) && array_key_exists('slots', $intent)) {
                $message->addExtras('slots', Collection::make($intent['slots']));
            }
            $this->messages = [$message];
        }

        return $this->messages;
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        $type = $this->event->get('type');
        if ($type === self::LAUNCH_REQUEST || $type === self::SESSION_ENDED_REQUEST) {
            $event = new GenericEvent($this->event);
            $event->setName($type);

            return $event;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = $additionalParameters;

        if ($message instanceof Question) {
            $text = $message->getText();
        } elseif ($message instanceof OutgoingMessage) {
            $text = $message->getText();
            $attachment = $message->getAttachment();
            if ($attachment instanceof Card) {
                $parameters['card'] = $attachment;
            }
        } else {
            $text = $message;
        }

        $parameters['text'] = $text;

        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $response = new AlexaResponse();
        if (is_string($payload['text'])) {
            $response->respondText($payload['text']);
        } elseif ($payload['text'] instanceof ContainerElement) {
            $response->respondSsml($payload['text']);
        }
        $response->card = $payload['card'] ?? null;
        $response->shouldEndSession = $payload['shouldEndSession'] ?? false;

        return Response::create(json_encode($response->render()))->send();
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return true;
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        //
    }

    

    public function dialogDelegate()
    {
        $response = [
            'version' => '1.0',
            'sessionAttributes' => [],
            'response' => [
                    'outputSpeech' => null,
                    'card' => null,
                    'directives' => [
                        [
                            'type' => 'Dialog.Delegate'
                        ]
                    ],
                    'reprompt' => null,
                    'shouldEndSession' => false,
            ]
        ];

        return Response::create(json_encode($response))->send();
    }
}
