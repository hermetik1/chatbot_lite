# Hermetic Transformation Course Platform

This is the repository for the Hermetic Transformation Course Platform, a production-grade web application for online courses.

## Project Overview

The goal is to build a comprehensive, conversion-optimized, and DSGVO-compliant course platform.

**Stack:**
- **Frontend:** Next.js 14 (App Router), TypeScript, Tailwind CSS, shadcn/ui
- **Backend:** Next.js API Routes / NestJS, PostgreSQL, Prisma
- **Authentication:** Email Magic Link & OAuth
- **Testing:** Jest, Playwright
- **CI/CD:** GitHub Actions

## Getting Started

### Prerequisites
- Node.js (v18 or later)
- pnpm
- Docker

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/hermetik1/chatbot_lite.git
   cd chatbot_lite
   ```

2. **Install dependencies:**
   ```bash
   pnpm install
   ```

3. **Set up environment variables:**
   Copy the `.env.example` to `.env` and fill in the required values.
   ```bash
   cp .env.example .env
   ```

4. **Set up the database:**
   Start the PostgreSQL database using Docker.
   ```bash
   docker-compose up -d
   ```

5. **Run database migrations:**
   ```bash
   pnpm prisma migrate dev
   ```

6. **Run the development server:**
   ```bash
   pnpm dev
   ```

Open [http://localhost:3000](http://localhost:3000) with your browser to see the result.
