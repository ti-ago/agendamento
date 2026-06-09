-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
-- Host: localhost    Database: login
-- Server version	11.8.6-MariaDB-6 from Debian

CREATE DATABASE IF NOT EXISTS `login` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
USE `login`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(140) NOT NULL,
  `email` varchar(140) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `email_confirmado` tinyint(1) DEFAULT 0,
  `confirmacao_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `users` (`id`, `nome`, `email`, `senha`) VALUES
(7, 'Teste', 'teste@teste.com', 'teste');

CREATE TABLE `agenda` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `nome_profissional` varchar(240) DEFAULT NULL,
  `foto_profissional` varchar(500) DEFAULT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `servico` varchar(240) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_user` (`id_user`),
  CONSTRAINT `fk_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rotinas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_agenda` int(11) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_termino` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_termino` time NOT NULL,
  `duracao` int(11) NOT NULL,
  `domingo` int(11) NOT NULL DEFAULT 0,
  `segunda` int(11) NOT NULL DEFAULT 0,
  `terca` int(11) NOT NULL DEFAULT 0,
  `quarta` int(11) NOT NULL DEFAULT 0,
  `quinta` int(11) NOT NULL DEFAULT 0,
  `sexta` int(11) NOT NULL DEFAULT 0,
  `sabado` int(11) NOT NULL DEFAULT 0,
  `cor` varchar(7) DEFAULT '#3465a4',
  PRIMARY KEY (`id`),
  KEY `fk_agenda` (`id_agenda`),
  CONSTRAINT `fk_agenda` FOREIGN KEY (`id_agenda`) REFERENCES `agenda` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `excessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_agenda` int(11) NOT NULL,
  `data` date NOT NULL,
  `data_termino` date DEFAULT NULL,
  `hora_inicio` time NOT NULL,
  `hora_termino` time NOT NULL,
  `domingo` int(11) NOT NULL DEFAULT 0,
  `segunda` int(11) NOT NULL DEFAULT 0,
  `terca` int(11) NOT NULL DEFAULT 0,
  `quarta` int(11) NOT NULL DEFAULT 0,
  `quinta` int(11) NOT NULL DEFAULT 0,
  `sexta` int(11) NOT NULL DEFAULT 0,
  `sabado` int(11) NOT NULL DEFAULT 0,
  `tipo` enum('bloqueado','reservado') NOT NULL DEFAULT 'bloqueado',
  PRIMARY KEY (`id`),
  KEY `excessoes_agenda` (`id_agenda`),
  CONSTRAINT `excessoes_agenda` FOREIGN KEY (`id_agenda`) REFERENCES `agenda` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `agendamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_agenda` int(11) NOT NULL,
  `data` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `cliente_nome` varchar(240) DEFAULT NULL,
  `token` varchar(20) NOT NULL,
  `status` enum('pendente','confirmado','cancelado','realizado') DEFAULT 'pendente',
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_agendamento_agenda` (`id_agenda`),
  CONSTRAINT `fk_agendamento_agenda` FOREIGN KEY (`id_agenda`) REFERENCES `agenda` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reset_user` (`id_user`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
