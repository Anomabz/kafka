-- SAPTO clean SQLite database export
-- Generated at 20260630-050203
PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;

DROP TABLE IF EXISTS "cache";
CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

DROP TABLE IF EXISTS "cache_locks";
CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));

DROP TABLE IF EXISTS "dosen";
CREATE TABLE "dosen" ("id" integer primary key autoincrement not null, "nama" varchar not null, "nidn" varchar, "nidk" varchar, "nip" varchar, "created_at" datetime, "updated_at" datetime);

DROP TABLE IF EXISTS "failed_jobs";
CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" varchar not null, "queue" varchar not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);

DROP TABLE IF EXISTS "job_batches";
CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));

DROP TABLE IF EXISTS "jobs";
CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

DROP TABLE IF EXISTS "mahasiswa_bimbingan_tugas_akhir";
CREATE TABLE "mahasiswa_bimbingan_tugas_akhir" ("id" integer primary key autoincrement not null, "mahasiswa_id" varchar, "nim" varchar, "nama_mahasiswa" varchar not null, "program_studi_id" integer not null, "dosen_id" integer not null, "judul_tugas_akhir" varchar, "jenis_tugas_akhir" varchar, "tahun" integer not null, "status" varchar, "sumber_data" varchar, "created_at" datetime, "updated_at" datetime, foreign key("program_studi_id") references "program_studi"("id"), foreign key("dosen_id") references "dosen"("id"));

DROP TABLE IF EXISTS "migrations";
CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (2, '0001_01_01_000001_create_cache_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (3, '0001_01_01_000002_create_jobs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (4, '2026_06_20_000001_create_program_studi_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (5, '2026_06_20_000002_create_dosen_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (6, '2026_06_20_000003_create_mahasiswa_bimbingan_tugas_akhir_table', 1);

DROP TABLE IF EXISTS "password_reset_tokens";
CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

DROP TABLE IF EXISTS "program_studi";
CREATE TABLE "program_studi" ("id" integer primary key autoincrement not null, "kode" varchar not null, "fakultas" varchar not null, "nama" varchar not null, "jenjang" varchar not null, "created_at" datetime, "updated_at" datetime);

DROP TABLE IF EXISTS "sessions";
CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

DROP TABLE IF EXISTS "users";
CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime);

CREATE INDEX "cache_expiration_index" on "cache" ("expiration");
CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");
CREATE INDEX "dosen_nama_index" on "dosen" ("nama");
CREATE INDEX "dosen_nidk_index" on "dosen" ("nidk");
CREATE INDEX "dosen_nidn_index" on "dosen" ("nidn");
CREATE INDEX "dosen_nip_index" on "dosen" ("nip");
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs" ("connection", "queue", "failed_at");
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");
CREATE INDEX "jobs_queue_index" on "jobs" ("queue");
CREATE INDEX "mahasiswa_bimbingan_tugas_akhir_dosen_id_tahun_index" on "mahasiswa_bimbingan_tugas_akhir" ("dosen_id", "tahun");
CREATE INDEX "mahasiswa_bimbingan_tugas_akhir_nim_index" on "mahasiswa_bimbingan_tugas_akhir" ("nim");
CREATE INDEX "mahasiswa_bimbingan_tugas_akhir_program_studi_id_tahun_index" on "mahasiswa_bimbingan_tugas_akhir" ("program_studi_id", "tahun");
CREATE INDEX "mahasiswa_bimbingan_tugas_akhir_tahun_index" on "mahasiswa_bimbingan_tugas_akhir" ("tahun");
CREATE INDEX "program_studi_fakultas_nama_index" on "program_studi" ("fakultas", "nama");
CREATE UNIQUE INDEX "program_studi_kode_unique" on "program_studi" ("kode");
CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");
CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");
CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

COMMIT;
PRAGMA foreign_keys=ON;
