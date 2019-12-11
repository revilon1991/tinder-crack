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

class DefaultController extends AbstractController
{
    private const ACCOUNT_KIT_TOKEN = 'EAAGm0PX4ZCpsBAKBmYB2t9ZBi7we4ZA2LKZC9g3Q4Id2LDQKb0ZAZBRBeVLgDNLimTubyjVQVgMo6egTXl48LZBX0fTKTtvlL6AbQbhzkL8NShbZASmMZBlZABGLHHixwCQX7cEHRZByZCJnljnlaIhzeKjZB3PTvGrklZBqwgRm5zVPKVhSaHEDa3ZAHd1lx1QCcd7ZBXAOLyXTGcE2io6ZCYDNKNZAUPVk6ZABwpNKhXd1Kxq90oCCAZDZD';
    private const ACCOUNT_KIT_START_LOGIN_URL = 'https://api.gotinder.com/v2/auth/sms/send';
    private const ACCOUNT_KIT_CONFIRM_LOGIN_URL = 'https://api.gotinder.com/v2/auth/sms/validate';
    private const TINDER_CONFIRM_LOGIN_URL = 'https://api.gotinder.com/v2/auth/login/sms';
    private const TINDER_MATCH_TEASERS = 'https://api.gotinder.com/v2/fast-match/teasers';

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
            $photoList = [];
            $client = new Client();

            $response = $client->get(self::TINDER_MATCH_TEASERS, [
                RequestOptions::HEADERS => [
                    'X-Auth-Token' => $apiToken,
                ],
                RequestOptions::HTTP_ERRORS => false,
            ]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                return $this->render('index.html.twig', [
                    'auth_block' => true,
                ]);
            }

            $data = json_decode($response->getBody()->getContents(), true);

            foreach ($data['data']['results'] as $result) {
                foreach ($result['user']['photos'] as $photo) {
                    $photoList[] = $photo['url'];
                }
            }

            return $this->render('index.html.twig', [
                'photo_list' => $photoList,
                'auth_block' => false,
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
            '{"phone_number":"%s","attempted_facebook_token":"%s"}',
            $phoneNumber,
            self::ACCOUNT_KIT_TOKEN
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

        $data = json_decode($response->getBody()->getContents(), true);
        $loginRequestCode = $data['login_request_code'];

        return new JsonResponse([
            'login_request_code' => $loginRequestCode,
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
            '{"phone_number":"%s","otp_code":"%s","attempted_facebook_token":"%s"}',
            $request->get('phone_number'),
            $request->get('confirmation_code'),
            self::ACCOUNT_KIT_TOKEN
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
            RequestOptions::QUERY => [
                'X-Auth-Token' => $request->get('api_token'),
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
}
