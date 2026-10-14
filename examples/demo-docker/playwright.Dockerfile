FROM mcr.microsoft.com/playwright:v1.64.1-noble
RUN npm install -g @playwright/cli@0.1.22 \
    && playwright-cli --help >/dev/null \
    && (npx -y playwright-core@1.64.0-alpha-1790635538000 install chromium || true)
WORKDIR /work
RUN playwright-cli install-browser firefox
