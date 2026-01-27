-- Ejecuta este script en MySQL Workbench
-- 1) Crea la BD
CREATE DATABASE IF NOT EXISTS new_hope_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE new_hope_platform;

-- 2) Tablas
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  correo VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) NULL,
  rol ENUM('ADMIN','DOCENTE','ESTUDIANTE') NOT NULL DEFAULT 'ESTUDIANTE',
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  cedula VARCHAR(30) NULL,
  nombre VARCHAR(120) NOT NULL,
  fecha_nacimiento DATE NULL,
  grado TINYINT NOT NULL,
  seccion VARCHAR(10) NULL,
  encargado VARCHAR(120) NULL,
  telefono_encargado VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (grado),
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  year SMALLINT NOT NULL,
  estado ENUM('ACTIVA','PENDIENTE') NOT NULL DEFAULT 'ACTIVA',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_year (student_id, year),
  CONSTRAINT fk_enr_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  from_user_id INT NOT NULL,
  to_user_id INT NULL,
  to_role ENUM('ADMIN','DOCENTE','ESTUDIANTE') NULL,
  asunto VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  INDEX (to_user_id),
  INDEX (to_role),
  CONSTRAINT fk_msg_from FOREIGN KEY (from_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_msg_to FOREIGN KEY (to_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  grado TINYINT NOT NULL,
  docente_user_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (grado),
  INDEX (docente_user_id),
  CONSTRAINT fk_course_docente FOREIGN KEY (docente_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (course_id),
  CONSTRAINT fk_section_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NOT NULL,
  size INT NOT NULL,
  uploaded_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (section_id),
  CONSTRAINT fk_res_section FOREIGN KEY (section_id) REFERENCES course_sections(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_res_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3) Usuario inicial ADMIN
-- Usuario: admin
-- Contraseña: admin123
INSERT INTO users (username, password_hash, nombre, correo, telefono, rol, estado)
VALUES ('admin', '$2y$10$M1ack1Y3bEBXcvOvymPAfOhIS3sW8Q5te.6Q7.jz4Il3PaHYhsZ2.', 'Administrador', 'admin@colegio.local', NULL, 'ADMIN', 'ACTIVO')
ON DUPLICATE KEY UPDATE username=username;
