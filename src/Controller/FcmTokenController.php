<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FcmTokenController extends AbstractController
{
    #[Route('/api/user/fcm-token', name: 'api_update_fcm_token', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateFcmToken(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $token = $data['fcm_token'] ?? null;

        if (!$token) {
            return new JsonResponse(['error' => 'fcm_token is required'], 400);
        }

        $user->setFcmToken($token);
        $entityManager->flush();

        return new JsonResponse(['message' => 'FCM token updated successfully']);
    }
}
