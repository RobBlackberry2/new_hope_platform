-- Schema para el Módulo de Reportes e Informes
-- Ejecutar después de schema.sql

USE new_hope_platform;

-- Tabla de calificaciones
CREATE TABLE IF NOT EXISTS grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  periodo VARCHAR(20) NOT NULL COMMENT 'I, II, III, etc.',
  calificacion DECIMAL(5,2) NOT NULL,
  docente_user_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student (student_id),
  INDEX idx_course (course_id),
  INDEX idx_periodo (periodo),
  CONSTRAINT fk_grade_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_grade_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_grade_docente FOREIGN KEY (docente_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de asistencia
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('PRESENTE','AUSENTE','TARDANZA') NOT NULL DEFAULT 'PRESENTE',
  course_id INT NULL COMMENT 'Puede ser por curso o general',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_student (student_id),
  INDEX idx_fecha (fecha),
  INDEX idx_course (course_id),
  UNIQUE KEY uq_student_date_course (student_id, fecha, course_id),
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_att_course FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de reportes generales
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('ACADEMICO','ASISTENCIA','RENDIMIENTO_INSTITUCIONAL','COMPARATIVO') NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT NULL,
  periodo_inicio DATE NULL,
  periodo_fin DATE NULL,
  datos_json TEXT NULL COMMENT 'Datos del reporte y versiones',
  created_by INT NOT NULL,
  estado ENUM('ACTIVO','ARCHIVADO') NOT NULL DEFAULT 'ACTIVO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tipo (tipo),
  INDEX idx_estado (estado),
  INDEX idx_periodo (periodo_inicio, periodo_fin),
  CONSTRAINT fk_report_creator FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de observaciones en reportes
CREATE TABLE IF NOT EXISTS report_observations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NULL COMMENT 'Puede ser NULL para observaciones generales',
  student_id INT NOT NULL,
  docente_user_id INT NOT NULL,
  observacion TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_report (report_id),
  INDEX idx_student (student_id),
  INDEX idx_docente (docente_user_id),
  CONSTRAINT fk_obs_report FOREIGN KEY (report_id) REFERENCES reports(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_obs_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_obs_docente FOREIGN KEY (docente_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de notificaciones de reportes
CREATE TABLE IF NOT EXISTS report_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  report_id INT NULL,
  tipo VARCHAR(50) NOT NULL COMMENT 'NUEVO_REPORTE, ACTUALIZACION, etc.',
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  INDEX idx_user (user_id),
  INDEX idx_report (report_id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_notif_report FOREIGN KEY (report_id) REFERENCES reports(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de relaciones padre-estudiante
CREATE TABLE IF NOT EXISTS parent_student_relations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_user_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_parent_student (parent_user_id, student_id),
  INDEX idx_parent (parent_user_id),
  INDEX idx_student (student_id),
  CONSTRAINT fk_rel_parent FOREIGN KEY (parent_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_rel_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
