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
        // Find by ID if numeric, otherwise find by Name
        $chatroom = is_numeric($id) ? $chatroomRepo->find((int)$id) : $chatroomRepo->findOneBy(['name' => $id]);

        if (!$chatroom) {
            return new JsonResponse(['status' => 'error', 'message' => 'Chatroom not found'], 404);
        }

        $messages = $messageRepo->findBy(
            ['chatroom' => $chatroom],
            ['sentAt' => 'ASC'],
            50
        );

        $data = array_map(function (ChatMessage $msg) {
            return [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'sender_id' => $msg->getSender()->getId(),
                'sender_name' => $msg->getSender()->getFirstName() ?? $msg->getSender()->getEmail(),
                'timestamp' => $msg->getSentAt()->format(\DateTime::ATOM),
            ];
        }, $messages);

        return new JsonResponse($data);
    }
}
