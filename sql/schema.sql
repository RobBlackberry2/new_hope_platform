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
  archived_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  year SMALLINT NOT NULL,
  start_year SMALLINT NOT NULL,
  estado ENUM('ACTIVA','PENDIENTE','BLOQUEADO') NOT NULL DEFAULT 'ACTIVA',
  archived_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_year (student_id, year),
  CONSTRAINT fk_enr_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS enrollment_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT NOT NULL,
  payment_year SMALLINT NOT NULL,
  month_key ENUM(
    'matricula',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre'
  ) NOT NULL,
  invoice_number VARCHAR(100) NULL,
  is_paid TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enrollment_year_month (enrollment_id, payment_year, month_key),
  CONSTRAINT fk_enrollment_payments_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
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
    ON UPDATE CASCADE ON DELETE SET NULL,
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
  tipo ENUM('RECURSOS','TAREA','QUIZ','EXAMEN','AVISO','FORO') NOT NULL DEFAULT 'RECURSOS',
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
  storage_provider VARCHAR(30) NOT NULL DEFAULT 'onedrive',
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
  weight_percent INT NULL,            
  max_score INT NOT NULL DEFAULT 100, 
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
  student_id INT NULL,     
  group_id INT NULL,       
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
  score INT NOT NULL,  
  feedback TEXT NULL,
  graded_by INT NOT NULL,   
  graded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  instructions TEXT NULL,
  time_limit_minutes INT NULL,         
  available_from DATETIME NULL,
  due_at DATETIME NULL,              
  weight_percent INT NULL,
  passing_score INT NOT NULL DEFAULT 70,
  show_results ENUM('NO','AFTER_SUBMIT','AFTER_DUE') NOT NULL DEFAULT 'AFTER_SUBMIT',
  is_exam TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
);

CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  type ENUM('MCQ','TF','SHORT') NOT NULL, 
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
  score INT NOT NULL DEFAULT 0,               
  raw_points INT NOT NULL DEFAULT 0,          
  max_points INT NOT NULL DEFAULT 0,          
  UNIQUE KEY uq_attempt_one (quiz_id, student_id), 
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE quiz_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT NULL, 
  answer_text TEXT NULL,      
  is_correct TINYINT(1) NULL,   
  points_awarded INT NULL,      
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS discussion_forums (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  forum_id INT NOT NULL,
  user_id INT NOT NULL,
  comment_body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (forum_id) REFERENCES discussion_forums(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_comment_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL,
  reported_by_user_id INT NOT NULL,
  reason TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_forum_comment_report (comment_id, reported_by_user_id),
  FOREIGN KEY (comment_id) REFERENCES forum_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS gamification_challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_by_user_id INT NOT NULL,
  tipo ENUM('RETO','MISION') NOT NULL DEFAULT 'RETO',
  titulo VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  instrucciones TEXT NULL,
  recompensa_tipo ENUM('MEDALLA','TROFEO') NOT NULL DEFAULT 'MEDALLA',
  recompensa_nombre VARCHAR(120) NOT NULL,
  fecha_inicio DATETIME NULL,
  fecha_fin DATETIME NULL,
  estado ENUM('BORRADOR','PUBLICADO','CERRADO') NOT NULL DEFAULT 'PUBLICADO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gamification_challenge_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gamification_enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id INT NULL,
  student_id INT NOT NULL,
  status ENUM('INSCRITO','DESINSCRITO') NOT NULL DEFAULT 'INSCRITO',
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unenrolled_at DATETIME NULL,
  UNIQUE KEY uq_gamification_challenge_student (challenge_id, student_id),
  CONSTRAINT fk_gamification_enrollment_challenge FOREIGN KEY (challenge_id) REFERENCES gamification_challenges(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_gamification_enrollment_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gamification_rewards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id INT NULL,
  student_id INT NOT NULL,
  assigned_by_user_id INT NOT NULL,
  reward_type ENUM('MEDALLA','TROFEO') NOT NULL,
  reward_name VARCHAR(120) NOT NULL,
  feedback TEXT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gamification_reward_challenge FOREIGN KEY (challenge_id) REFERENCES gamification_challenges(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_gamification_reward_student FOREIGN KEY (student_id) REFERENCES students(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_gamification_reward_user FOREIGN KEY (assigned_by_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS onedrive_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refresh_token TEXT NOT NULL,
  access_token TEXT NULL,
  expires_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3) Usuario inicial ADMIN
-- Usuario: admin
-- Contraseña: admin123
INSERT INTO users (username, password_hash, nombre, correo, telefono, rol, estado)
VALUES ('admin', '$2y$10$M1ack1Y3bEBXcvOvymPAfOhIS3sW8Q5te.6Q7.jz4Il3PaHYhsZ2.', 'Administrador', 'admin@colegio.local', NULL, 'ADMIN', 'ACTIVO')
ON DUPLICATE KEY UPDATE username=username;




