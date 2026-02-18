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
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  year SMALLINT NOT NULL,
  estado ENUM('ACTIVA','PENDIENTE','BLOQUEADO') NOT NULL DEFAULT 'ACTIVA',
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
  seccion VARCHAR(10) NOT NULL,
  docente_user_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_course_docente FOREIGN KEY (docente_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion VARCHAR(200),
  semana INT NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo ENUM('RECURSOS','TAREA','QUIZ','EXAMEN','AVISO') NOT NULL DEFAULT 'RECURSOS',
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
  storage_provider VARCHAR(20) NOT NULL DEFAULT 'local',
  storage_item_id VARCHAR(255) NULL,
  public_url TEXT NULL,
  CONSTRAINT fk_res_section FOREIGN KEY (section_id) REFERENCES course_sections(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_res_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  instructions TEXT NULL,
  due_at DATETIME NULL,
  is_group TINYINT(1) NOT NULL DEFAULT 0,
  weight_percent INT NULL,            -- % dentro del curso (si aplica)
  max_score INT NOT NULL DEFAULT 100, -- base 100 por defecto
  passing_score INT NOT NULL DEFAULT 70,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
);

CREATE TABLE submission_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE submission_group_members (
  group_id INT NOT NULL,
  student_id INT NOT NULL,
  PRIMARY KEY (group_id, student_id),
  FOREIGN KEY (group_id) REFERENCES submission_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  student_id INT NULL,     -- si entrega individual
  group_id INT NULL,       -- si entrega grupal
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('BORRADOR','ENVIADA') NOT NULL DEFAULT 'ENVIADA',
  UNIQUE KEY uq_submission_ind (assignment_id, student_id),
  UNIQUE KEY uq_submission_grp (assignment_id, group_id),
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);

CREATE TABLE submission_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NULL,
  size INT NOT NULL DEFAULT 0,
  storage_provider VARCHAR(20) NOT NULL DEFAULT 'local',
  storage_item_id VARCHAR(255) NULL,
  public_url TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);

CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL UNIQUE,
  score INT NOT NULL,        -- 0..max_score
  feedback TEXT NULL,
  graded_by INT NOT NULL,    -- user_id (docente/admin)
  graded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  instructions TEXT NULL,
  time_limit_minutes INT NULL,          -- null = sin límite
  available_from DATETIME NULL,
  due_at DATETIME NULL,                  -- fecha cierre / entrega
  passing_score INT NOT NULL DEFAULT 70, -- aprueba >= 70
  show_results ENUM('NO','AFTER_SUBMIT','AFTER_DUE') NOT NULL DEFAULT 'AFTER_SUBMIT',
  is_exam TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
);

CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  type ENUM('MCQ','TF','SHORT') NOT NULL, -- multiple choice, true/false, short answer
  question_text TEXT NOT NULL,
  points INT NOT NULL DEFAULT 10,
  orden INT NOT NULL DEFAULT 0,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE quiz_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

CREATE TABLE quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  student_id INT NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  status ENUM('IN_PROGRESS','SUBMITTED','GRADED') NOT NULL DEFAULT 'IN_PROGRESS',
  score INT NOT NULL DEFAULT 0,               -- 0..100 (porcentaje)
  raw_points INT NOT NULL DEFAULT 0,          -- puntos obtenidos
  max_points INT NOT NULL DEFAULT 0,          -- total puntos quiz
  UNIQUE KEY uq_attempt_one (quiz_id, student_id), -- MVP: 1 intento
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE quiz_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT NULL,  -- para MCQ/TF
  answer_text TEXT NULL,        -- para SHORT
  is_correct TINYINT(1) NULL,   -- null para SHORT sin calificar
  points_awarded INT NULL,      -- null para SHORT sin calificar
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);


-- 3) Usuario inicial ADMIN
-- Usuario: admin
-- Contraseña: admin123
INSERT INTO users (username, password_hash, nombre, correo, telefono, rol, estado)
VALUES ('admin', '$2y$10$M1ack1Y3bEBXcvOvymPAfOhIS3sW8Q5te.6Q7.jz4Il3PaHYhsZ2.', 'Administrador', 'admin@colegio.local', NULL, 'ADMIN', 'ACTIVO')
ON DUPLICATE KEY UPDATE username=username;


