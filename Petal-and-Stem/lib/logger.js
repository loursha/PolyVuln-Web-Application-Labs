const fs = require('fs');
const path = require('path');

const LOG_DIR = path.join(__dirname, '..', 'logs');
const LOG_FILE = path.join(LOG_DIR, 'activity.log');
fs.mkdirSync(LOG_DIR, { recursive: true });

function activity(event, details) {
  const line = `${new Date().toISOString()} ${event} ${Object.entries(details).map(([k, v]) => `${k}=${String(v).replace(/\s+/g, ' ')}`).join(' ')}`;
  fs.appendFile(LOG_FILE, line + '\n', () => {});
  console.log('[activity]', line);
}

function tail(lines = 200) {
  try {
    const data = fs.readFileSync(LOG_FILE, 'utf8').trim().split('\n');
    return data.slice(-lines).reverse();
  } catch { return []; }
}

module.exports = { activity, tail, LOG_FILE };
