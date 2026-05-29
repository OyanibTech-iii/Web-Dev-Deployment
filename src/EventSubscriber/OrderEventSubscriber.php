<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Service\NotificationService;
use App\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

class OrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private UserRepository $userRepository
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        // Notify admins about new order
        $this->notifyAdmins(
            'New Order #' . $entity->getId(),
            'A new order has been placed by ' . ($entity->getCustomer() ? $entity->getCustomer()->getFullName() : 'Guest'),
            $entity
        );

        // Also notify the customer that their order was created
        if ($entity->getCustomer()) {
            $this->notificationService->create(
                $entity->getCustomer(),
                'Order Placed',
                'Your order #' . $entity->getId() . ' has been placed successfully!',
                'order',
                'medium'
            );
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);

        if (isset($changeSet['status'])) {
            $oldStatus = $changeSet['status'][0];
            $newStatus = $changeSet['status'][1];

            // Notify admins about status update (important for real-time table refresh)
            $this->notifyAdmins(
                'Order #' . $entity->getId() . ' Status Updated',
                'Order status changed from ' . $oldStatus . ' to ' . $newStatus . '.',
                $entity
            );

            if ($entity->getCustomer()) {
                $this->notificationService->create(
                    $entity->getCustomer(),
                    'Order Status Updated',
                    'Your order #' . $entity->getId() . ' status has been changed from ' . $oldStatus . ' to ' . $newStatus . '.',
                    'order',
                    'medium'
                );
            }
        }
    }

    /**
     * Helper to notify all admins
     */
    private function notifyAdmins(string $title, string $message, Order $order): void
    {
        // Fetch all users with ROLE_ADMIN or ROLE_SUPER_ADMIN
        // Using a more robust LIKE pattern for JSON roles
        $admins = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :admin')
            ->orWhere('u.roles LIKE :superadmin')
            ->setParameter('admin', '%"ROLE_ADMIN"%')
            ->setParameter('superadmin', '%"ROLE_SUPER_ADMIN"%')
            ->getQuery()
            ->getResult();

        foreach ($admins as $admin) {
            $this->notificationService->create(
                $admin,
                $title,
                $message,
                'order',
                'high'
            );
        }
    }
}
