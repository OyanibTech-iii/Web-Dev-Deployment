package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"sync"

	firebase "firebase.google.com/go/v4"
	"firebase.google.com/go/v4/messaging"
	"github.com/gorilla/websocket"
	"github.com/redis/go-redis/v9"
	"google.golang.org/api/option"
)

var (
	fcmClient *messaging.Client
	fcmOnce   sync.Once
)

func getFCMClient() *messaging.Client {
	fcmOnce.Do(func() {
		ctx := context.Background()
		var app *firebase.App
		var err error

		serviceAccountPath := os.Getenv("FIREBASE_SERVICE_ACCOUNT_PATH")
		if serviceAccountPath != "" {
			opt := option.WithCredentialsFile(serviceAccountPath)
			app, err = firebase.NewApp(ctx, nil, opt)
		} else {
			// Fallback to default credentials or environment variable GOOGLE_APPLICATION_CREDENTIALS
			app, err = firebase.NewApp(ctx, nil)
		}

		if err != nil {
			log.Printf("Error initializing Firebase app: %v", err)
			return
		}

		client, err := app.Messaging(ctx)
		if err != nil {
			log.Printf("Error getting Messaging client: %v", err)
			return
		}
		fcmClient = client
	})
	return fcmClient
}

func sendPushNotification(deviceToken string, title string, body string) {
	client := getFCMClient()
	if client == nil {
		log.Println("FCM client not initialized, skipping push notification")
		return
	}

	ctx := context.Background()
	message := &messaging.Message{
		Notification: &messaging.Notification{
			Title: title,
			Body:  body,
		},
		Token: deviceToken,
	}

	response, err := client.Send(ctx, message)
	if err != nil {
		log.Printf("Error sending push notification: %v", err)
		return
	}
	fmt.Printf("Successfully sent push notification: %s\n", response)
}

type NotificationHub struct {
	Clients    map[*websocket.Conn]bool
	Broadcast  chan []byte
	Register   chan *websocket.Conn
	Unregister chan *websocket.Conn
	mu         sync.Mutex
	redis      *redis.Client
}

func NewNotificationHub(rdb *redis.Client) *NotificationHub {
	return &NotificationHub{
		Clients:    make(map[*websocket.Conn]bool),
		Broadcast:  make(chan []byte),
		Register:   make(chan *websocket.Conn),
		Unregister: make(chan *websocket.Conn),
		redis:      rdb,
	}
}

func (h *NotificationHub) ListenRedis() {
	ctx := context.Background()
	pubsub := h.redis.Subscribe(ctx, "notifications")
	defer pubsub.Close()

	ch := pubsub.Channel()
	for msg := range ch {
		h.Broadcast <- []byte(msg.Payload)
	}
}

func (h *NotificationHub) Run() {
	for {
		select {
		case conn := <-h.Register:
			h.mu.Lock()
			h.Clients[conn] = true
			h.mu.Unlock()
			fmt.Println("Notification client registered")

		case conn := <-h.Unregister:
			h.mu.Lock()
			if _, ok := h.Clients[conn]; ok {
				delete(h.Clients, conn)
				conn.Close()
			}
			h.mu.Unlock()
			fmt.Println("Notification client unregistered")

		case message := <-h.Broadcast:
			h.mu.Lock()
			for conn := range h.Clients {
				err := conn.WriteMessage(websocket.TextMessage, message)
				if err != nil {
					log.Printf("Notification write error: %v", err)
					conn.Close()
					delete(h.Clients, conn)
				}
			}
			h.mu.Unlock()
		}
	}
}

func ServeNotificationsWs(hub *NotificationHub, w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println(err)
		return
	}
	hub.Register <- conn
}

// Notification structure for JSON responses
type NotificationEvent struct {
	Count int    `json:"count"`
	Type  string `json:"type"` // "badge_update" or "new_notification"
}
