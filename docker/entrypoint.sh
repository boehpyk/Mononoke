#!/bin/bash

set -e

SCRIPT="/app/examples/sns.php"
PID=0

start_script() {
  echo "[Watcher] Starting script..."
  php "$SCRIPT" &
  PID=$!
}

stop_script() {
  if kill -0 "$PID" 2>/dev/null; then
    echo "[Watcher] Stopping script with PID $PID..."
    kill "$PID"
    wait "$PID" 2>/dev/null
  fi
}

handle_exit() {
  echo "[Watcher] Shutting down..."
  stop_script
  exit 0
}

trap handle_exit SIGINT SIGTERM

start_script

echo "[Watcher] Watching for .php file changes..."

while true; do
  inotifywait -r -e modify,create,delete --format '%w%f' /app/src /app/examples 2>/dev/null \
    | grep '\.php$' \
    | while read changed_file; do
        echo "[Watcher] Change detected in: $changed_file"
        stop_script
        start_script
      done

  echo "[Watcher] Watcher loop restarted after inotifywait exited."
  sleep 1
done
