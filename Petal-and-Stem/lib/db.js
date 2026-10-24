const mysql = require('mysql2/promise');

const config = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'petal_app',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'petalstem',
  waitForConnections: true,
  connectionLimit: 10,
  multipleStatements: false, // no stacked queries
  dateStrings: true,
  charset: 'utf8mb4'
};

const pool = mysql.createPool(config);

async function q(sql, params = []) {
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function waitForDb(retries = 40) {
  for (let i = 1; i <= retries; i++) {
    try { await pool.query('SELECT 1'); return; }
    catch (e) {
      console.log(`[db] waiting for MySQL (${i}/${retries}): ${e.code || e.message}`);
      await new Promise(r => setTimeout(r, 3000));
    }
  }
  throw new Error('Database not reachable');
}

module.exports = { pool, q, waitForDb, config };
