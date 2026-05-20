-- Marketplace phase 1: roles, sellers and ownership columns.
-- Run this after backing up the database.
-- Target: MariaDB/MySQL used by XAMPP.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Official roles.
-- Legacy `user` rows become `cliente`.
ALTER TABLE usuarios
  MODIFY role ENUM('admin','vendedor','cliente','user') NULL DEFAULT 'cliente';

UPDATE usuarios
SET role = 'cliente'
WHERE role = 'user' OR role IS NULL OR role = '';

ALTER TABLE usuarios
  MODIFY role ENUM('admin','vendedor','cliente') NULL DEFAULT 'cliente';

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER estado,
  ADD COLUMN IF NOT EXISTS seller_status ENUM('pendiente','activo','suspendido') NOT NULL DEFAULT 'activo' AFTER created_by;

ALTER TABLE usuarios
  ADD INDEX IF NOT EXISTS idx_usuarios_role (role),
  ADD INDEX IF NOT EXISTS idx_usuarios_created_by (created_by);

-- 2) Seller public profile metadata.
CREATE TABLE IF NOT EXISTS vendedor_perfiles (
  id INT NOT NULL AUTO_INCREMENT,
  vendedor_id INT NOT NULL,
  tienda_nombre VARCHAR(120) NOT NULL,
  descripcion TEXT NULL,
  logo_url VARCHAR(255) NULL,
  banner_url VARCHAR(255) NULL,
  soporte_whatsapp VARCHAR(20) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendedor_perfiles_vendedor (vendedor_id),
  CONSTRAINT fk_vendedor_perfiles_usuario
    FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Product/account/sale ownership.
ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS vendedor_id INT NULL AFTER id,
  ADD COLUMN IF NOT EXISTS estado_revision ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado' AFTER activo,
  ADD COLUMN IF NOT EXISTS rechazo_motivo TEXT NULL AFTER estado_revision;

ALTER TABLE productos
  ADD INDEX IF NOT EXISTS idx_productos_vendedor (vendedor_id),
  ADD INDEX IF NOT EXISTS idx_productos_estado_revision (estado_revision);

ALTER TABLE cuentas
  ADD COLUMN IF NOT EXISTS vendedor_id INT NULL AFTER id,
  ADD COLUMN IF NOT EXISTS modo_venta ENUM('PERFIL','CUENTA_COMPLETA') NULL DEFAULT NULL AFTER producto_id;

ALTER TABLE cuentas
  ADD INDEX IF NOT EXISTS idx_cuentas_vendedor (vendedor_id),
  ADD INDEX IF NOT EXISTS idx_cuentas_producto_vendedor (producto_id, vendedor_id);

ALTER TABLE compras
  ADD COLUMN IF NOT EXISTS cliente_id INT NULL AFTER id,
  ADD COLUMN IF NOT EXISTS vendedor_id INT NULL AFTER cliente_id,
  ADD COLUMN IF NOT EXISTS cuenta_id INT NULL AFTER producto_id,
  ADD COLUMN IF NOT EXISTS perfil_id INT NULL AFTER cuenta_id,
  ADD COLUMN IF NOT EXISTS comision_admin DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto,
  ADD COLUMN IF NOT EXISTS monto_vendedor DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER comision_admin;

ALTER TABLE compras
  ADD INDEX IF NOT EXISTS idx_compras_cliente (cliente_id),
  ADD INDEX IF NOT EXISTS idx_compras_vendedor (vendedor_id),
  ADD INDEX IF NOT EXISTS idx_compras_producto_vendedor (producto_id, vendedor_id);

-- Backfill cliente_id from legacy usuario_id.
UPDATE compras
SET cliente_id = usuario_id
WHERE cliente_id IS NULL;

-- 4) Assign legacy catalog to a platform seller.
-- If there is no seller yet, the first admin is used as owner of legacy stock.
UPDATE productos p
JOIN (
  SELECT id FROM usuarios
  WHERE role IN ('vendedor','admin')
  ORDER BY FIELD(role, 'vendedor', 'admin'), id
  LIMIT 1
) owner ON 1=1
SET p.vendedor_id = owner.id
WHERE p.vendedor_id IS NULL;

UPDATE cuentas c
JOIN productos p ON p.id = c.producto_id
SET c.vendedor_id = p.vendedor_id,
    c.modo_venta = COALESCE(c.modo_venta, p.tipo_venta)
WHERE c.vendedor_id IS NULL OR c.modo_venta IS NULL;

UPDATE compras co
JOIN productos p ON p.id = co.producto_id
SET co.vendedor_id = p.vendedor_id,
    co.monto_vendedor = CASE WHEN co.monto_vendedor = 0 THEN co.monto ELSE co.monto_vendedor END
WHERE co.vendedor_id IS NULL;

-- 5) Wallet audit trail. Client saldo still lives in usuarios.saldo for now,
-- but every future movement should also be recorded here.
CREATE TABLE IF NOT EXISTS saldo_movimientos (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  actor_id INT NULL,
  tipo ENUM('recarga','compra','ajuste_admin','venta','comision','reembolso') NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  saldo_anterior DECIMAL(10,2) NULL,
  saldo_nuevo DECIMAL(10,2) NULL,
  referencia_tipo VARCHAR(50) NULL,
  referencia_id INT NULL,
  nota VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_saldo_movimientos_usuario (usuario_id),
  KEY idx_saldo_movimientos_actor (actor_id),
  KEY idx_saldo_movimientos_ref (referencia_tipo, referencia_id),
  CONSTRAINT fk_saldo_movimientos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_saldo_movimientos_actor
    FOREIGN KEY (actor_id) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
