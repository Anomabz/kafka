import sqlite3
import mysql.connector

# 1. Koneksi ke SQLite
sqlite_conn = sqlite3.connect('database/database.sqlite')
sqlite_cursor = sqlite_conn.cursor()

# 2. Koneksi ke MySQL (Container Docker)
# Gunakan 127.0.0.1 karena port 3306 sudah di-publish ke luar container
mysql_conn = mysql.connector.connect(
    host="127.0.0.1",
    user="root",
    password="rootpassword",
    database="sapto_db",
    port=3306
)
mysql_cursor = mysql_conn.cursor()

# Ambil semua daftar tabel dari SQLite (kecuali tabel internal sqlite)
sqlite_cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';")
tables = sqlite_cursor.fetchall()

print("Memulai migrasi data...")

for table in tables:
    table_name = table[0]
    print(f"Memindahkan data untuk tabel: {table_name}...")
    
    # Ambil data dari SQLite
    sqlite_cursor.execute(f"SELECT * FROM `{table_name}`")
    rows = sqlite_cursor.fetchall()
    
    if not rows:
        continue

    # Buat query placeholder untuk MySQL (?, ?, ?) -> (%s, %s, %s)
    placeholders = ", ".join(["%s"] * len(rows[0]))
    
    # Kosongkan tabel MySQL terlebih dahulu agar tidak duplikat (opsional)
    try:
        mysql_cursor.execute(f"SET FOREIGN_KEY_CHECKS = 0;")
        mysql_cursor.execute(f"TRUNCATE TABLE `{table_name}`;")
        
        # Insert data ke MySQL
        insert_query = f"INSERT INTO `{table_name}` VALUES ({placeholders})"
        mysql_cursor.executemany(insert_query, rows)
        mysql_conn.commit()
    except Exception as e:
        print(f"Gagal di tabel {table_name}: {e}")

mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 1;")
print("Migrasi Selesai Sukses!")

sqlite_conn.close()
mysql_conn.close()
