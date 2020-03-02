<?php

declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function GuzzleHttp\json_decode;

class DefaultController extends AbstractController
{
    private const ACCOUNT_KIT_START_LOGIN_URL = 'https://api.gotinder.com/v2/auth/sms/send';
    private const ACCOUNT_KIT_CONFIRM_LOGIN_URL = 'https://api.gotinder.com/v2/auth/sms/validate';
    private const TINDER_CONFIRM_LOGIN_URL = 'https://api.gotinder.com/v2/auth/login/sms';
    private const TINDER_MATCH_TEASERS = 'https://api.gotinder.com/v2/fast-match/teasers';
    private const TINDER_ADMIRER_MATCH = 'https://api.gotinder.com/v2/fast-match/secret-admirer/rate';
    private const TINDER_ADMIRER_REVEAL = 'https://api.gotinder.com/v2/fast-match/secret-admirer';

    /**
     * @Route(path="/")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request): Response
    {
        $apiToken = $request->cookies->get('api_token');

        if ($apiToken) {
            $userList = [];
            $client = new Client();

            $response = $client->get(self::TINDER_MATCH_TEASERS, [
                RequestOptions::HEADERS => [
                    'X-Auth-Token' => $apiToken,
                ],
                RequestOptions::HTTP_ERRORS => false,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                return $this->render('index.html.twig', [
                    'auth_block' => true,
                ]);
            }

            foreach ($data['data']['results'] as $result) {
                foreach ($result['user']['photos'] as $photo) {
                    $uid = $result['user']['_id'];

                    $userList[$uid] = [
                        'photoUrl' => $photo['url'],
                    ];
                }
            }

            $response = $client->get(self::TINDER_ADMIRER_REVEAL, [
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::HEADERS => [
                    'X-Auth-Token' => $apiToken,
                ],
            ]);
            $dataAdmirer = $response->getBody()->getContents();
            $dataAdmirer = \json_decode($dataAdmirer, true);
            $admirerAvailableMatch = $dataAdmirer['data']['results'][0]['user']['_id'] ?? null;

            return $this->render('index.html.twig', [
                'user_list' => $userList,
                'auth_block' => false,
                'admirerMatchUid' => $admirerAvailableMatch,
            ]);
        }

        return $this->render('index.html.twig', [
            'auth_block' => true,
        ]);
    }

    /**
     * @Route(path="/startLogin")
     *
     * @param Request $request
     *
     * @param LoggerInterface $logger
     *
     * @return JsonResponse
     */
    public function accountKitStartLogin(Request $request, LoggerInterface $logger): JsonResponse
    {
        $phoneNumber = preg_replace('#\D#', '', $request->get('phone_number'));

        $client = new Client();

        $body = sprintf(
            '{"phone_number":"%s"}',
            $phoneNumber
        );

        $response = $client->post(self::ACCOUNT_KIT_START_LOGIN_URL, [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
            RequestOptions::QUERY => [
                'auth_type' => 'sms',
            ],
            RequestOptions::BODY => $body,

        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $logger->error(sprintf(
                'Error on %s: %s',
                self::ACCOUNT_KIT_START_LOGIN_URL,
                $response->getBody()->getContents()
            ));

            throw new \RuntimeException('Error ' . self::ACCOUNT_KIT_START_LOGIN_URL);
        }

        return new JsonResponse([
            'phone_number' => $phoneNumber,
        ]);
    }

    /**
     * @Route(path="/confirmLogin")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function confirmLogin(Request $request): JsonResponse
    {
        $client = new Client();

        $body = sprintf(
            '{"phone_number":"%s","otp_code":"%s"}',
            $request->get('phone_number'),
            $request->get('confirmation_code')
        );

        // account kit auth
        $response = $client->post(self::ACCOUNT_KIT_CONFIRM_LOGIN_URL, [
            RequestOptions::QUERY => [
                'auth_type' => 'sms',
            ],
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
            RequestOptions::BODY => $body,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $refreshToken = $data['data']['refresh_token'];

//        // tinder auth
        $body = sprintf(
            '{"refresh_token":"%s","client_version":"11.4.0"}',
            $refreshToken
        );

        $response = $client->post(self::TINDER_CONFIRM_LOGIN_URL, [
            RequestOptions::BODY => $body,
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $refreshToken = $data['data']['refresh_token'];
        $apiToken = $data['data']['api_token'];

        return new JsonResponse([
            'api_token' => $apiToken,
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @Route(path="/tinder/revealMatch")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function tinderRevealMatch(Request $request): JsonResponse
    {
        $client = new Client();

        $response = $client->post(self::TINDER_MATCH_TEASERS, [
            RequestOptions::HEADERS => [
                'X-Auth-Token' => $request->get('api_token'),
                'Content-Type' => 'application/json',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $photoList = [];

        foreach ($data['data']['results'] as $result) {
            foreach ($result['user']['photos'] as $photo) {
                $photoList[] = $photo['url'];
            }
        }

        return new JsonResponse([
            'photo_list' => $photoList,
        ]);
    }

    /**
     * @Route(path="/tinder/goMatch", methods={"POST"})
     *
     * @param Request $request
     * @param LoggerInterface $logger
     *
     * @return JsonResponse
     */
    public function tinderGoMatch(Request $request, LoggerInterface $logger): JsonResponse
    {
        $client = new Client();

        $body = sprintf(
            '{"uid":"%s","type":"like"}',
            $request->get('uid')
        );

        $response = $client->post(self::TINDER_ADMIRER_MATCH, [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::BODY => $body,
            RequestOptions::HEADERS => [
                'X-Auth-Token' => $request->get('api_token'),
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        switch (true) {
            case $response->getStatusCode() === Response::HTTP_OK:
                return new JsonResponse([
                    'message' => 'Успех. Ищите этого человека у себя в парах',
                ]);

            case !empty($data['err']['data']['next_available_game']):
                $response = $client->get(self::TINDER_ADMIRER_REVEAL, [
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::HEADERS => [
                        'X-Auth-Token' => $request->get('api_token'),
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $nextAvailableGame = (string)$data['err']['data']['next_available_game'];
                $nextAvailableGame = (int)substr($nextAvailableGame, 0, 10);

                return new JsonResponse([
                    'message' => 'Следующий матч возможен: ' . date('d.m.Y H:i:s', $nextAvailableGame),
                ]);

            default:
                $logger->error('Error parse response tinder go match', [
                    'response' => $response->getBody()->getContents(),
                    'code' => $response->getStatusCode(),
                    'body' => $body,
                ]);

                throw new \RuntimeException('Error ' . self::TINDER_ADMIRER_MATCH);
        }
    }
}
