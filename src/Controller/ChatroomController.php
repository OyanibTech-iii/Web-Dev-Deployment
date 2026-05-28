<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\UserRepository;
use App\Entity\Chatroom;
use App\Entity\ChatMessage;
use App\Message\ChatMessage as ChatMessageBus;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/chatroom')]
#[IsGranted('ROLE_USER')]
class ChatroomController extends AbstractController
{
    #[Route('/', name: 'app_chatroom')]
    public function index(UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        return $this->showChatroom('Main Discussion', $userRepository, $em);
    }

    #[Route('/private/{id}', name: 'app_chatroom_private')]
    public function privateChat(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $targetUser = $userRepository->find($id);
        if (!$targetUser) {
            return $this->redirectToRoute('app_chatroom');
        }

        if ($targetUser === $this->getUser()) {
            return $this->redirectToRoute('app_chatroom');
        }

        // Create a unique name for 1-on-1 chat - Unified with mobile
        $userIds = [$this->getUser()->getId(), $targetUser->getId()];
        sort($userIds);
        $chatroomName = 'private_' . implode('_', $userIds);

        return $this->showChatroom($chatroomName, $userRepository, $em, [$this->getUser(), $targetUser], $targetUser);
    }

    private function showChatroom(string $name, UserRepository $userRepository, EntityManagerInterface $em, array $participants = [], ?\App\Entity\User $selectedUser = null): Response
    {
        // Fetch all users for the chat list (carousel)
        $users = $userRepository->findAll();

        // Get or create the chatroom
        $chatroomRepo = $em->getRepository(Chatroom::class);
        $chatroom = $chatroomRepo->findOneBy(['name' => $name]);
        
        if (!$chatroom) {
            $chatroom = new Chatroom();
            $chatroom->setName($name);
            
            if (empty($participants)) {
                // Default to some logic? For now, let's just make it public or limited
                // If it's "Main Discussion", maybe don't add all users as participants to entity?
                // Actually, the original logic added all admins.
            } else {
                foreach ($participants as $participant) {
                    $chatroom->addParticipant($participant);
                }
            }
            
            $em->persist($chatroom);
            $em->flush();
        }

        // Fetch last 50 messages
        $messages = $em->getRepository(ChatMessage::class)->findBy(
            ['chatroom' => $chatroom],
            ['sentAt' => 'ASC'],
            50
        );

        return $this->render('chatroom/index.html.twig', [
            'user' => $this->getUser(),
            'users' => $users,
            'chatroom' => $chatroom,
            'messages' => $messages,
            'selectedUser' => $selectedUser,
        ]);
    }

    #[Route('/save-message', name: 'app_chatroom_save', methods: ['POST'])]
    public function saveMessage(Request $request, MessageBusInterface $bus): JsonResponse
    {
        $chatroomId = $request->request->get('chatroom_id');
        $content = $request->request->get('content');

        if (!$chatroomId || !$content) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $bus->dispatch(new ChatMessageBus(
            (string)$content,
            ChatMessageBus::TYPE_USER,
            [
                'chatroom_id' => $chatroomId,
                'sender_id' => $this->getUser()->getId(),
            ]
        ));

        return new JsonResponse([
            'status' => 'success'
        ]);
    }
}
