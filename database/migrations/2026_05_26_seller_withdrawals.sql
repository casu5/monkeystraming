CREATE TABLE IF NOT EXISTS vendedor_retiros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendedor_id INT NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  metodo VARCHAR(50) NOT NULL,
  cuenta_destino VARCHAR(190) NOT NULL,
  estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  nota TEXT NULL,
  admin_nota TEXT NULL,
  comprobante_url VARCHAR(255) NULL,
  comprobante_subido_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revisado_en DATETIME NULL,
  KEY idx_vendedor_retiros_vendedor (vendedor_id),
  KEY idx_vendedor_retiros_estado (estado),
  KEY idx_vendedor_retiros_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE vendedor_retiros
  ADD COLUMN IF NOT EXISTS comprobante_url VARCHAR(255) NULL AFTER admin_nota,
  ADD COLUMN IF NOT EXISTS comprobante_subido_en DATETIME NULL AFTER comprobante_url;
