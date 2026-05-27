<?php

namespace App\Service\Chat;

use App\Entity\ChatMessage;
use App\Entity\Chatroom;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;

class AdminChatService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RedisClient $redis
    ) {
    }

    public function handleMessage(int|string $chatroomId, int $senderId, string $content): void
    {
        if (is_numeric($chatroomId)) {
            $chatroom = $this->entityManager->getRepository(Chatroom::class)->find($chatroomId);
        } else {
            // Find or create chatroom by name (e.g., 'private_X_Y')
            $chatroom = $this->entityManager->getRepository(Chatroom::class)->findOneBy(['name' => $chatroomId]);
            if (!$chatroom) {
                $chatroom = new Chatroom();
                $chatroom->setName((string)$chatroomId);
                
                // If it's a private chat, try to add participants
                if (preg_match('/private_(\d+)_(\d+)/', (string)$chatroomId, $matches)) {
                    $user1 = $this->entityManager->getRepository(User::class)->find($matches[1]);
                    $user2 = $this->entityManager->getRepository(User::class)->find($matches[2]);
                    if ($user1) $chatroom->addParticipant($user1);
                    if ($user2) $chatroom->addParticipant($user2);
                }
                
                $this->entityManager->persist($chatroom);
                $this->entityManager->flush();
            }
        }
        
        $sender = $this->entityManager->getRepository(User::class)->find($senderId);

        if (!$chatroom || !$sender) {
            return;
        }

        $message = new ChatMessage();
        $message->setChatroom($chatroom);
        $message->setSender($sender);
        $message->setContent($content);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        // Publish to Redis for real-time delivery via Go microservice
        $this->redis->publish('chat', json_encode([
            'chatroom_id' => $chatroomId,
            'sender_id' => $senderId,
            'sender_name' => $sender->getFirstName(),
            'sender_image' => $sender->getProfileImage(),
            'content' => $content,
            'type' => 'message'
        ]));
    }
}
