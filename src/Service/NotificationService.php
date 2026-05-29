<?php 
namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RedisClient $redis
    ) {}

    public function create(User $user, string $title, string $message, string $type = 'info', string $priority = 'low'): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setPriority($priority);
        $notification->setIsRead(false);
        $notification->setCreateAt(new \DateTimeImmutable());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Publish to Redis for real-time delivery via Go microservice
        $this->redis->publish('notifications', json_encode([
            'user_id' => $user->getId(),
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'event' => 'new_notification',
            'fcm_token' => $user->getFcmToken()
        ]));
    }
}