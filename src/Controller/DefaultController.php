<?php

declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    private const ACCOUNT_KIT_TOKEN = 'AA%7C464891386855067%7Cd1891abb4b0bcdfa0580d9b839f4a522';
    private const ACCOUNT_KIT_START_LOGIN_URL = 'https://graph.accountkit.com/v1.3/start_login';
    private const ACCOUNT_KIT_CONFIRM_LOGIN_URL = 'https://graph.accountkit.com/v1.3/confirm_login';
    private const TINDER_CONFIRM_LOGIN_URL = 'https://api.gotinder.com/v2/auth/login/accountkit';
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
     * @return JsonResponse
     */
    public function accountKitStartLogin(Request $request): JsonResponse
    {
        $phoneNumber = preg_replace('#\D#', '', $request->get('phone_number'));

        $client = new Client();

        $response = $client->post(self::ACCOUNT_KIT_START_LOGIN_URL, [
            RequestOptions::QUERY => [
                'access_token' => self::ACCOUNT_KIT_TOKEN,
                'credentials_type' => 'phone_number',
                'response_type' => 'token',
                'phone_number' => $phoneNumber,
            ]
        ]);

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

        // account kit auth
        $response = $client->post(self::ACCOUNT_KIT_CONFIRM_LOGIN_URL, [
            RequestOptions::QUERY => [
                'access_token' => self::ACCOUNT_KIT_TOKEN,
                'confirmation_code' => $request->get('confirmation_code'),
                'credentials_type' => 'phone_number',
                'login_request_code' => $request->get('login_request_code'),
                'phone_number' => $request->get('phone_number'),
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $confirmLoginAccessToken = $data['access_token'];
        $confirmLoginId = $data['id'];

        // tinder auth
        $body = sprintf(
            '{"token":"%s","client_version":"11.4.0","id":"%s"}',
            $confirmLoginAccessToken,
            $confirmLoginId
        );

        $response = $client->post(self::TINDER_CONFIRM_LOGIN_URL, [
            RequestOptions::BODY => $body,
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
