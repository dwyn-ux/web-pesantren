SET NAMES utf8mb4;

-- ============================================================
-- Migration 024: Seed key pengaturan notifikasi Telegram
--   - telegram_chat_ids: daftar chat ID tujuan (grup + pribadi,
--     pisahkan koma). Diisi admin via /admin/pengaturan.
--     Token bot disimpan di .env (TELEGRAM_BOT_TOKEN).
-- Aman dijalankan ulang (idempotent).
-- ============================================================

INSERT IGNORE INTO `pengaturan` (`key_name`, `value`, `label`) VALUES
('telegram_chat_ids', '', 'Chat ID Telegram tujuan notifikasi (koma-separated)');
