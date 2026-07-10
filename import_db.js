const fs = require('fs');
const mysql = require('mysql2/promise');

async function importDb() {
  console.log("Connecting to Aiven MySQL...");
  try {
    const connection = await mysql.createConnection({
      host: 'mysql-3a0ec4f4-qrattend.f.aivencloud.com',
      port: 24181,
      user: process.env.AIVEN_USER || 'avnadmin',
      password: process.env.AIVEN_PASSWORD,
      database: process.env.AIVEN_DATABASE || 'defaultdb',
      ssl: {
        rejectUnauthorized: false
      },
      multipleStatements: true
    });

    console.log("Connected successfully! Reading SQL file...");
    const sql = fs.readFileSync('qrattend.sql', 'utf8');

    console.log("Executing SQL...");
    await connection.query('SET SESSION sql_require_primary_key = 0;');
    await connection.query(sql);

    console.log("Database import complete! Closing connection...");
    await connection.end();
  } catch (error) {
    console.error("Error during import:", error);
  }
}

importDb();
