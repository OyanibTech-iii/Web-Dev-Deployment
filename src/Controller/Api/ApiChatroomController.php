<?php

namespace App\Controller\Api;

use App\Entity\ChatMessage;
use App\Entity\Chatroom;
use App\Message\ChatMessage as ChatMessageBus;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatroomRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chatroom')]
class ApiChatroomController extends AbstractController
{
    #[Route('/save', name: 'api_chatroom_save', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveMessage(Request $request, MessageBusInterface $bus): JsonResponse
    {
        // Handle both JSON and FormData
        $data = json_decode($request->getContent(), true);
        $chatroomId = $request->request->get('chatroom_id') ?? $data['chatroom_id'] ?? null;
        $content = $request->request->get('content') ?? $data['content'] ?? null;

        if (!$chatroomId || !$content) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $bus->dispatch(new ChatMessageBus(
            (string)$content,
            'user', // Matches ChatMessage::TYPE_USER
            [
                'chatroom_id' => $chatroomId,
                'sender_id' => $this->getUser()->getId(),
            ]
        ));

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/{id}', name: 'api_chatroom_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMessages(
        string $id,
        ChatroomRepository $chatroomRepo,
        ChatMessageRepository $messageRepo
    ): JsonResponse {
        // 1. Find the chatroom
        $chatroom = is_numeric($id) ? $chatroomRepo->find((int)$id) : $chatroomRepo->findOneBy(['name' => $id]);

        if (!$chatroom) {
            return new JsonResponse(['status' => 'error', 'message' => 'Chatroom not found'], 404);
        }

        // 2. Fetch messages (latest 50, then reverse to chronological)
        $messages = $messageRepo->findBy(['chatroom' => $chatroom], ['sentAt' => 'DESC'], 50);
        $messages = array_reverse($messages);

        // 3. Map safely with NULL checks
        $data = array_map(function (ChatMessage $msg) {
            $sender = $msg->getSender();

            // PREVENT 500: Check if sender exists
            $senderId = $sender ? $sender->getId() : 0;
            $senderName = 'User';
            if ($sender) {
                $senderName = $sender->getFirstName() ?? $sender->getEmail() ?? 'User';
            }

            return [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                // PREVENT 500: Check if sentAt exists
                'timestamp' => $msg->getSentAt() ? $msg->getSentAt()->format(\DateTime::ATOM) : date(\DateTime::ATOM),
            ];
        }, $messages);

        return new JsonResponse($data);
    }
}
