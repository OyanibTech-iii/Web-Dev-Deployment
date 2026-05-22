FROM golang:1.23-alpine AS builder

WORKDIR /app

# Install git for fetching dependencies if needed
RUN apk add --no-cache git

COPY go.mod go.sum ./
RUN go mod download

COPY . .

# Build the binary
RUN CGO_ENABLED=0 GOOS=linux go build -o chat-service .

FROM alpine:latest

WORKDIR /root/

# Install CA certificates for secure connections
RUN apk add --no-cache ca-certificates

COPY --from=builder /app/chat-service .

# Port is provided by Railway
ENV PORT=8080
EXPOSE 8080

CMD ["./chat-service"]
