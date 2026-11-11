// Parcours Playwright (tests/e2e) : « npm run e2e » après « npm run build ». Le serveur (tests/e2e/serve.sh) recrée une
// base SQLite dédiée et sert public/ sur 127.0.0.1 (contexte sûr pour le service worker). Chromium : celui de
// Playwright, ou PW_CHROMIUM (exécutable fourni par l'environnement, ex. /opt/pw-browsers/chromium).
import { defineConfig, devices } from '@playwright/test';

const PORT = Number(process.env.E2E_PORT || 8123);

export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 90_000,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    serviceWorkers: 'allow',
    locale: 'fr-FR',
    launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: `sh tests/e2e/serve.sh ${PORT}`,
    url: `http://127.0.0.1:${PORT}/up`,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
