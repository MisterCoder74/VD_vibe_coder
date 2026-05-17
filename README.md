# Vivacity Vibe Coder

A multi-user platform for generating and deploying micro-apps using AI.

## Setup

1. Ensure you have PHP installed with the `curl` and `zip` extensions.
2. Initialize `users.json` as an empty array: `[]`.
3. Create a `users/` directory with write permissions for the web server.
4. Configure your web server to serve the project directory.

## Features

- **Multi-user system**: Authentication and session management.
- **Credit system**: Track AI token usage as credits.
- **AI-powered generation**: Decompose projects into tasks and execute them.
- **Live Preview**: See your app in real-time as it's built.
- **Deployment**: One-click deployment to a unique URL.
- **Management**: Dashboard to view, delete, and download your micro-apps.

## Security

- User data is isolated in `users/{client_id}/`.
- Direct access to JSON files is blocked via `.htaccess`.
- OpenAI API keys are stored on the server and never exposed to the client.
