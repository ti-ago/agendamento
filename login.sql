-- phpMyAdmin SQL Dump
-- version 5.2.3deb1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 30/04/2026 às 16:56
-- Versão do servidor: 11.8.6-MariaDB-6 from Debian
-- Versão do PHP: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `login`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `agenda`
--

CREATE TABLE `agenda` (
  `id` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `servico` varchar(240) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `agenda`
--

INSERT INTO `agenda` (`id`, `id_user`, `servico`) VALUES
(21, 7, 'teste');

-- --------------------------------------------------------

--
-- Estrutura para tabela `rotinas`
--

CREATE TABLE `rotinas` (
  `id` int(11) NOT NULL,
  `id_agenda` int(11) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_termino` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_termino` time DEFAULT NULL,
  `duracao` int(11) DEFAULT NULL,
  `domingo` int(11) NOT NULL DEFAULT 0,
  `segunda` int(11) NOT NULL DEFAULT 0,
  `terca` int(11) NOT NULL DEFAULT 0,
  `quarta` int(11) NOT NULL DEFAULT 0,
  `quinta` int(11) NOT NULL DEFAULT 0,
  `sexta` int(11) NOT NULL DEFAULT 0,
  `sabado` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `rotinas`
--

INSERT INTO `rotinas` (`id`, `id_agenda`, `data_inicio`, `data_termino`, `hora_inicio`, `hora_termino`, `duracao`, `domingo`, `segunda`, `terca`, `quarta`, `quinta`, `sexta`, `sabado`) VALUES
(14, 21, '2026-05-02', '2026-05-30', '08:00:00', '18:00:00', 60, 0, 1, 0, 1, 0, 1, 0),
(15, 21, '2026-05-01', '2026-05-30', '13:00:00', '17:00:00', 30, 0, 0, 1, 0, 1, 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nome` varchar(140) NOT NULL,
  `email` varchar(140) NOT NULL,
  `senha` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `nome`, `email`, `senha`) VALUES
(7, 'Teste', 'teste@teste.com', 'teste');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `agenda`
--
ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user` (`id_user`);

--
-- Índices de tabela `rotinas`
--
ALTER TABLE `rotinas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_agenda` (`id_agenda`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `agenda`
--
ALTER TABLE `agenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `rotinas`
--
ALTER TABLE `rotinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;


-- Restrições para tabelas `agenda`
--
ALTER TABLE `agenda`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`);


ALTER TABLE `rotinas`
  ADD CONSTRAINT `fk_agenda` FOREIGN KEY (`id_agenda`) REFERENCES `agenda` (`id`);
COMMIT;

CREATE TABLE `excessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_agenda` int(11) NOT NULL FOREIGN KEY REFERENCES `agenda`(`id`),
  `data` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_termino` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
