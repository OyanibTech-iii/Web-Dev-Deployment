<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class GoogleController extends AbstractController
{
    #[Route('/api/login/google', name: 'api_login_google', methods: ['POST'])]
    public function mobileGoogleLogin(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['id_token'] ?? null;

        if (!$idToken) {
            return new JsonResponse(['message' => 'Missing id_token'], 400);
        }

        // 1. Verify Google Token
        // Use the Web Client ID from your App.tsx:
        $client = new GoogleClient(['client_id' => '1059016108118-ra4ljvtgf8q8etespns2bpf3b4ekqut1.apps.googleusercontent.com']);
        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            return new JsonResponse(['message' => 'Invalid Google token'], 401);
        }

        $email = $payload['email'];
        $googleId = $payload['sub'];

        // 2. Find or Create User
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Create new account if user doesn't exist
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName($payload['given_name'] ?? 'Google');
            $user->setLastName($payload['family_name'] ?? 'User');
            $user->setGoogleId($googleId);
            $user->setProvider('google');
            $user->setIsVerified(true);
            // Set a random password since they login via Google
            $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(16))));
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);
            $em->flush();

            error_log("Created new Google user: " . $email);
        } else {
            // Ensure Google ID is linked if they previously registered with email
            if (!$user->getGoogleId()) {
                $user->setGoogleId($googleId);
                $user->setProvider('google');
                $em->flush();
            }
        }

        // 3. Generate JWT Token
        $token = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ]
        ], 200);
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectAction(ClientRegistry $clientRegistry)
    {
        // Redirects to Google
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email', 'profile' 
            ]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(Request $request)
    {
        // This remains empty! The bundle's authenticator will intercept this route.
    }
}
