const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright");

async function main() {
  const root = path.resolve(__dirname, "..", "..");
  const outputDir = path.join(root, "poc-artifacts");
  const finalVideo = path.join(outputDir, "glotpress-real-app-dummy-data-poc.webm");

  fs.mkdirSync(outputDir, { recursive: true });

  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    recordVideo: {
      dir: outputDir,
      size: { width: 1440, height: 900 },
    },
  });

  const page = await context.newPage();
  page.on("console", (message) => console.log(`browser console ${message.type()}: ${message.text()}`));
  page.on("pageerror", (error) => console.log(`browser pageerror: ${error.message}`));

  await page.goto("http://127.0.0.1:8899/real-app-bulk-boundary-poc.php", {
    waitUntil: "networkidle",
  });
  await page.getByRole("button", { name: "Run real GlotPress PoC with dummy data" }).click();
  await page.waitForTimeout(8000);
  const logText = await page.locator("#log").innerText();
  console.log(logText);
  if (!logText.includes('"translation_changed": true') || !logText.includes('"priority_changed": true')) {
    throw new Error("PoC result did not show both unauthorized cross-scope changes.");
  }
  await page.waitForTimeout(2500);

  const video = page.video();
  await page.close();
  await context.close();
  await browser.close();

  const recordedPath = await video.path();
  if (fs.existsSync(finalVideo)) {
    fs.unlinkSync(finalVideo);
  }
  fs.renameSync(recordedPath, finalVideo);

  console.log(`Video saved to ${finalVideo}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
