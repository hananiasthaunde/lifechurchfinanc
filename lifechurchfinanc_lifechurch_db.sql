-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 03/02/2026 às 02:39
-- Versão do servidor: 8.0.37
-- Versão do PHP: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `lifechurchfinanc_lifechurch_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `attendances`
--

CREATE TABLE `attendances` (
  `id` int NOT NULL,
  `member_id` int NOT NULL,
  `report_id` int NOT NULL,
  `is_present` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('entrada','saida') COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `type`) VALUES
(1, 'Dízimos', 'entrada'),
(2, 'Ofertas de Culto', 'entrada'),
(3, 'Ofertas Especiais', 'entrada'),
(4, 'Aluguel do Templo', 'saida'),
(5, 'Água e Energia', 'saida'),
(8, 'Ação Social', 'saida'),
(10, 'Fundo apostolico', 'saida'),
(12, 'OFERTAS', 'entrada'),
(13, 'OFERTA ESPECIAL', 'entrada'),
(14, 'SUBSIDIOS', 'entrada'),
(15, 'ALUGUER DE SALAO', 'saida'),
(16, 'VIAGENS E TRANSPORTE', 'saida'),
(17, 'EQUIPAMENTOS', 'saida'),
(18, 'COMBUSTIVEL', 'saida'),
(19, 'REPAROS E MANUTENCAO', 'saida'),
(20, 'COMUNICACAO', 'saida'),
(21, 'ADMINISTRACAO', 'saida'),
(22, 'ELECTRICIDADE', 'saida'),
(23, 'CUSTOS BANCARIOS', 'saida'),
(24, 'CONTINGENCIA/OUTROS', 'saida'),
(25, 'REUNIOES', 'saida'),
(26, 'MATERIAL', 'saida'),
(27, 'DIVIDAS', 'saida'),
(30, 'Outros saldos', 'saida'),
(86, 'MIA', 'saida'),
(87, 'Construção ', 'saida'),
(88, 'Salário e Seguro ', 'saida');

-- --------------------------------------------------------

--
-- Estrutura para tabela `celulas`
--

CREATE TABLE `celulas` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `lider_id` int NOT NULL,
  `church_id` int NOT NULL,
  `dia_semana` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `horario` time DEFAULT NULL,
  `endereco` text COLLATE utf8mb4_general_ci,
  `dia_encontro` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Saturday',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `celulas`
--

INSERT INTO `celulas` (`id`, `nome`, `lider_id`, `church_id`, `dia_semana`, `horario`, `endereco`, `dia_encontro`, `created_at`) VALUES
(4, 'Celula de irmao Ananias', 23, 3, NULL, '15:00:00', 'Chingodzi', 'Saturday', '2025-07-01 21:33:02'),
(5, 'Ananias Chingodzi', 46, 7, NULL, '15:00:00', 'Chingodzi', 'Sunday', '2026-01-31 12:08:25'),
(6, 'Life church Tete ', 45, 7, NULL, '16:00:00', 'Chingodzi ', 'Saturday', '2026-01-31 12:16:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `celula_relatorios`
--

CREATE TABLE `celula_relatorios` (
  `id` int NOT NULL,
  `celula_id` int NOT NULL,
  `lider_id` int NOT NULL,
  `mes_referencia` varchar(7) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Formato YYYY-MM',
  `participacoes_membros_json` text COLLATE utf8mb4_general_ci,
  `visitantes_json` text COLLATE utf8mb4_general_ci,
  `candidatos_batismo_json` text COLLATE utf8mb4_general_ci,
  `eventos_celula_json` text COLLATE utf8mb4_general_ci,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `churches`
--

CREATE TABLE `churches` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `balance` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `churches`
--

INSERT INTO `churches` (`id`, `name`, `balance`) VALUES
(3, 'Tete', 9398.40),
(4, 'Chimoio', 114840.00),
(6, 'Nicoadala', 14416.00),
(7, 'Life church Tete', 7389.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `church_id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `paid_to` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `received_by` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_general_ci,
  `transaction_date` datetime DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Despejando dados para a tabela `expenses`
--

INSERT INTO `expenses` (`id`, `church_id`, `user_id`, `category_id`, `amount`, `description`, `paid_to`, `received_by`, `comments`, `transaction_date`) VALUES
(51, 3, 2, 25, 49.00, '[{\"description\":\"Saída de Abril - Reuniões\",\"quantity\":1,\"unit_price\":49.00,\"total\":49.00}]', NULL, NULL, NULL, '2025-04-30 00:00:00'),
(52, 3, 2, 15, 3000.00, '[{\"description\":\"Aluguer de salão (Maio)\",\"quantity\":1,\"unit_price\":3000.00,\"total\":3000.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(53, 3, 2, 10, 2138.00, '[{\"description\":\"Fundo Apostólico (Maio)\",\"quantity\":1,\"unit_price\":2138.00,\"total\":2138.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(54, 3, 2, 16, 4398.00, '[{\"description\":\"Viagens e Transporte (Maio)\",\"quantity\":1,\"unit_price\":4398.00,\"total\":4398.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(55, 3, 2, 17, 450.00, '[{\"description\":\"Equipamentos (Maio)\",\"quantity\":1,\"unit_price\":450.00,\"total\":450.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(56, 3, 2, 19, 1150.00, '[{\"description\":\"Reparos e Manutenção (Maio)\",\"quantity\":1,\"unit_price\":1150.00,\"total\":1150.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(57, 3, 2, 21, 134.00, '[{\"description\":\"Administração (Maio)\",\"quantity\":1,\"unit_price\":134.00,\"total\":134.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(58, 3, 2, 22, 100.00, '[{\"description\":\"Electricidade (Maio)\",\"quantity\":1,\"unit_price\":100.00,\"total\":100.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(59, 3, 2, 24, 200.00, '[{\"description\":\"Contingência/Outros (Maio)\",\"quantity\":1,\"unit_price\":200.00,\"total\":200.00}]', NULL, NULL, NULL, '2025-05-31 00:00:00'),
(60, 3, 2, 15, 3016.00, '[{\"description\":\"Aluguer de salão (Junho)\",\"quantity\":1,\"unit_price\":3016.00,\"total\":3016.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(61, 3, 2, 10, 1378.00, '[{\"description\":\"Fundo Apostólico (Junho)\",\"quantity\":1,\"unit_price\":1378.00,\"total\":1378.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(62, 3, 2, 16, 403.00, '[{\"description\":\"Viagens e Transporte (Junho)\",\"quantity\":1,\"unit_price\":403.00,\"total\":403.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(63, 3, 2, 17, 400.00, '[{\"description\":\"Equipamentos (Junho)\",\"quantity\":1,\"unit_price\":400.00,\"total\":400.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(64, 3, 2, 20, 23.00, '[{\"description\":\"Comunicação (Junho)\",\"quantity\":1,\"unit_price\":23.00,\"total\":23.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(65, 3, 2, 21, 342.00, '[{\"description\":\"Administração (Junho)\",\"quantity\":1,\"unit_price\":342.00,\"total\":342.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(66, 3, 2, 22, 100.00, '[{\"description\":\"Electricidade (Junho)\",\"quantity\":1,\"unit_price\":100.00,\"total\":100.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(67, 3, 2, 24, 208.00, '[{\"description\":\"Contingência/Outros (Junho)\",\"quantity\":1,\"unit_price\":208.00,\"total\":208.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(68, 3, 2, 25, 1320.00, '[{\"description\":\"Reuniões (Junho)\",\"quantity\":1,\"unit_price\":1320.00,\"total\":1320.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(69, 3, 2, 27, 4500.00, '[{\"description\":\"Dívidas (Junho)\",\"quantity\":1,\"unit_price\":4500.00,\"total\":4500.00}]', NULL, NULL, NULL, '2025-06-30 00:00:00'),
(70, 3, 2, 15, 3016.00, '[{\"description\":\"Aluguer de salão (Julho)\",\"quantity\":1,\"unit_price\":3016.00,\"total\":3016.00}]', NULL, NULL, NULL, '2025-07-31 00:00:00'),
(71, 3, 2, 10, 2237.00, '[{\"description\":\"Fundo Apostólico (Julho)\",\"quantity\":1,\"unit_price\":2237.00,\"total\":2237.00}]', NULL, NULL, NULL, '2025-07-31 00:00:00'),
(72, 3, 2, 22, 50.00, '[{\"description\":\"Electricidade (Julho)\",\"quantity\":1,\"unit_price\":50.00,\"total\":50.00}]', NULL, NULL, NULL, '2025-07-31 00:00:00'),
(73, 3, 2, 24, 208.00, '[{\"description\":\"Contingência/Outros (Julho)\",\"quantity\":1,\"unit_price\":208.00,\"total\":208.00}]', NULL, NULL, NULL, '2025-07-31 00:00:00'),
(74, 3, 2, 24, 50.00, '[{\"description\":\"Ajuste de saldo para conciliação com relatório manual.\",\"quantity\":1,\"unit_price\":50.00,\"total\":50.00}]', NULL, NULL, NULL, '2025-07-31 00:00:00'),
(75, 3, 2, 16, 6240.00, '[{\"description\":\"Viagem de pastores para confer\\u00eancia\",\"quantity\":1,\"unit_price\":6240,\"total\":6240}]', 'Viagem', 'Pastores', '', '2025-07-18 00:00:00'),
(77, 3, 2, 86, 188.00, '[{\"description\":\"Caderno\",\"quantity\":1,\"unit_price\":100,\"total\":100},{\"description\":\"Caneta\",\"quantity\":8,\"unit_price\":10,\"total\":80},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":8,\"total\":8}]', 'Loja comercial', 'Bernardino', '', '2025-07-19 00:00:00'),
(81, 4, 42, 87, 10100.00, '[{\"description\":\"Barrotes \",\"quantity\":15,\"unit_price\":500,\"total\":7500},{\"description\":\"Transporte \",\"quantity\":1,\"unit_price\":500,\"total\":500},{\"description\":\"\\u00d3leo queimado e pintura \",\"quantity\":1,\"unit_price\":1500,\"total\":1500},{\"description\":\"Pinc\\u00e9is \",\"quantity\":2,\"unit_price\":200,\"total\":400},{\"description\":\"Pregos\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'Mercado informal ', 'Ramos Dengua ', 'Finalização de instalação de madeiras para coberturas ', '2025-08-01 00:00:00'),
(82, 3, 2, 10, 779.00, '[{\"description\":\"Fundo apostolico\",\"quantity\":1,\"unit_price\":771,\"total\":771},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":8,\"total\":8}]', ' ', 'Ananias Thaunde', '', '2025-08-08 00:00:00'),
(83, 3, 2, 4, 3016.00, '[{\"description\":\"Valor de renda\",\"quantity\":1,\"unit_price\":3000,\"total\":3000},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Grupo desportivo de Tete', 'Ananias Thaunde', '', '2025-08-08 00:00:00'),
(86, 4, 42, 87, 530.00, '[{\"description\":\"Pagamento de parcela de m\\u00e3o de obra para cobertura \",\"quantity\":1,\"unit_price\":500,\"total\":500},{\"description\":\"Transfer\\u00eancia bom e-mola \",\"quantity\":1,\"unit_price\":30,\"total\":30}]', 'Pagamento de mestres ', 'Ramos Dengua ', '', '2025-08-05 00:00:00'),
(87, 4, 42, 88, 3100.00, '[{\"description\":\"Parcelas de Sal\\u00e1rio de Mar\\u00e7o (\\u00faltima parcela 1550) e\",\"quantity\":1,\"unit_price\":1550,\"total\":1550},{\"description\":\"Parcelas de Sal\\u00e1rio de Mar\\u00e7o (primeira parcela 1550) e\",\"quantity\":1,\"unit_price\":1550,\"total\":1550}]', 'Rui Paqueliua', 'Carlitos Rafael ', '', '2025-08-11 00:00:00'),
(88, 4, 42, 23, 120.00, '[{\"description\":\"Custo banc\\u00e1rio da transfer\\u00eancia interbanc\\u00e1ria de sal\\u00e1rio \",\"quantity\":1,\"unit_price\":120,\"total\":120}]', 'Millennium bim ', 'Carlitos Rafael ', '', '2025-08-11 00:00:00'),
(89, 3, 2, 17, 10000.00, '[{\"description\":\"Mesa de som\",\"quantity\":1,\"unit_price\":10000,\"total\":10000}]', '1', 'Elísio', '', '2025-08-13 00:00:00'),
(90, 4, 42, 5, 100.00, '[{\"description\":\"Credelec \",\"quantity\":1,\"unit_price\":100,\"total\":100}]', 'Compra de credelec', 'Ramos Dengua ', '', '2025-08-17 00:00:00'),
(91, 4, 42, 26, 100.00, '[{\"description\":\"L\\u00e2mpada\",\"quantity\":1,\"unit_price\":100,\"total\":100}]', 'Entregue ao proprietário da casa onde está o material da igreja ', 'Ramos Dengua ', '', '2025-08-17 00:00:00'),
(92, 3, 2, 16, 500.00, '[{\"description\":\"Gasolina\",\"quantity\":1,\"unit_price\":500,\"total\":500}]', 'Bombas de gasolina', 'Família Muler', '', '2025-09-08 00:00:00'),
(93, 3, 2, 17, 730.00, '[{\"description\":\"Equipamentos\",\"quantity\":1,\"unit_price\":730,\"total\":730}]', 'Equipamentos', 'Irmão Gene', '', '2025-08-20 00:00:00'),
(94, 3, 2, 16, 200.00, '[{\"description\":\"Gasolina\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'Bombas de gasolina', 'Família Muler', '', '2025-08-20 00:00:00'),
(95, 3, 2, 17, 508.00, '[{\"description\":\"Vassoura\",\"quantity\":1,\"unit_price\":470,\"total\":470},{\"description\":\"Vassoura de palha\",\"quantity\":2,\"unit_price\":15,\"total\":30},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":8,\"total\":8}]', 'Supermercado Love Shopping', 'Irmão Áchimo', '', '2025-08-30 00:00:00'),
(96, 3, 2, 4, 3016.00, '[{\"description\":\"Renda\",\"quantity\":1,\"unit_price\":3000,\"total\":3000},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Campo desportivo de Tete', 'Irmão Ananias', '', '2025-09-04 00:00:00'),
(97, 3, 2, 10, 1273.00, '[{\"description\":\"Fundo apostolico\",\"quantity\":1,\"unit_price\":1257,\"total\":1257},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Fundo apostolico', 'Irmão Ananias', '', '2025-09-04 00:00:00'),
(99, 3, 2, 25, 108.00, '[{\"description\":\"Ceia e c\\u00f3pias\",\"quantity\":1,\"unit_price\":108,\"total\":108}]', 'Desconhecido', 'Pastora Neyma', '', '2025-09-06 00:00:00'),
(100, 3, 2, 18, 200.00, '[{\"description\":\"Gasolina\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'Bombas de gasolina', 'Pastores', '', '2025-09-29 00:00:00'),
(102, 3, 2, 17, 707.00, '[{\"description\":\"Equipamentos\",\"quantity\":1,\"unit_price\":707,\"total\":707}]', 'KANAIA', 'Irmão Gene', '', '2025-09-29 00:00:00'),
(104, 3, 2, 16, 215.00, '[{\"description\":\"Taxi\",\"quantity\":1,\"unit_price\":208,\"total\":208},{\"description\":\"taxa emola para m-pesa\",\"quantity\":1,\"unit_price\":7,\"total\":7}]', ' ', 'Pastor', 'Valor usado para transporte e ver terreno', '2025-09-29 00:00:00'),
(105, 3, 2, 16, 645.00, '[{\"description\":\"Tranporte e ceia\",\"quantity\":1,\"unit_price\":634,\"total\":634},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":11,\"total\":11}]', 'Bombas de gasolina', 'Pastora Neyma', '', '2025-09-29 00:00:00'),
(106, 3, 2, 18, 500.00, '[{\"description\":\"Transporte e ceia\",\"quantity\":1,\"unit_price\":500,\"total\":500}]', 'Bombas de gasolina', 'Pastora Neyma', '', '2025-09-29 00:00:00'),
(107, 3, 2, 16, 83.00, '[{\"description\":\"Chapa\",\"quantity\":1,\"unit_price\":83,\"total\":83}]', 'Chapa', 'Irmão Eduardo', '', '2025-09-29 00:00:00'),
(108, 3, 2, 25, 1000.00, '[{\"description\":\"Encontro de mulheres\",\"quantity\":1,\"unit_price\":1000,\"total\":1000}]', 'Mercado', 'Pastora Neyma', '', '2025-09-29 00:00:00'),
(110, 3, 2, 17, 8500.00, '[{\"description\":\"Cadeiras\",\"quantity\":1,\"unit_price\":8500,\"total\":8500}]', 'Super mercado Sol', 'Pastores', '', '2025-09-29 00:00:00'),
(112, 3, 2, 16, 83.00, '[{\"description\":\"Transporte\",\"quantity\":4,\"unit_price\":20,\"total\":80},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":3,\"total\":3}]', 'Chapas', 'Irmão Eduardo ', '', '2025-10-04 00:00:00'),
(113, 3, 2, 15, 3016.00, '[{\"description\":\"Renda\",\"quantity\":1,\"unit_price\":3000,\"total\":3000},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Grupo desportivo de Tete', 'Irmão Ananias', '', '2025-10-07 00:00:00'),
(114, 3, 2, 19, 1500.00, '[{\"description\":\"Manuten\\u00e7ao de mesa de som\",\"quantity\":1,\"unit_price\":1500,\"total\":1500}]', 'Desconhecido', 'Irmão Gene', '', '2025-10-07 00:00:00'),
(115, 3, 2, 16, 556.00, '[{\"description\":\"Transporte\",\"quantity\":1,\"unit_price\":83,\"total\":83},{\"description\":\"Manuten\\u00e7ao de energia\",\"quantity\":1,\"unit_price\":350,\"total\":350},{\"description\":\"Transporte\",\"quantity\":1,\"unit_price\":60,\"total\":60},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":3,\"total\":3},{\"description\":\"Transporte\",\"quantity\":1,\"unit_price\":60,\"total\":60}]', 'Transporte', 'Irmão Eduardo', 'Valores usados para transporte para células, e busca de equipamentos, os 350 foram usados para alugar o material para subir o poste.', '2025-10-11 00:00:00'),
(116, 3, 2, 16, 65.00, '[{\"description\":\"Transporte\",\"quantity\":1,\"unit_price\":65,\"total\":65}]', 'Transporte', 'Irmã Cacilda', 'Valor enviado para mana Cacilda, para que saísse de Moatize a cidade de Tete e pudesse participar do culto', '2025-10-11 00:00:00'),
(117, 3, 2, 21, 297.00, '[{\"description\":\"Agua \",\"quantity\":1,\"unit_price\":170,\"total\":170},{\"description\":\"Caderno\",\"quantity\":1,\"unit_price\":75,\"total\":75},{\"description\":\"Caneta\",\"quantity\":1,\"unit_price\":10,\"total\":10},{\"description\":\" \",\"quantity\":1,\"unit_price\":25,\"total\":25},{\"description\":\" \",\"quantity\":1,\"unit_price\":10,\"total\":10},{\"description\":\"taxa\",\"quantity\":1,\"unit_price\":7,\"total\":7}]', 'Socote S.N gerencia', 'Irmão achimo', 'Troco 25mt', '2025-10-14 00:00:00'),
(118, 3, 2, 21, 212.00, '[{\"description\":\"Crachas\",\"quantity\":1,\"unit_price\":150,\"total\":150},{\"description\":\"Bandeja\",\"quantity\":1,\"unit_price\":50,\"total\":50},{\"description\":\"Plastico\",\"quantity\":1,\"unit_price\":5,\"total\":5},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":7,\"total\":7}]', 'Cidade', 'Juelma e Achimo', '', '2025-10-14 00:00:00'),
(119, 3, 2, 30, 2500.00, '[{\"description\":\"Empr\\u00e9stimo \",\"quantity\":1,\"unit_price\":2500,\"total\":2500}]', 'Empréstimo ', 'Pastores', '', '2025-10-14 00:00:00'),
(120, 3, 2, 17, 671.00, '[{\"description\":\"Celular\",\"quantity\":1,\"unit_price\":660,\"total\":660},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":11,\"total\":11}]', 'PEP', 'Pastores', '', '2025-10-18 00:00:00'),
(121, 3, 2, 15, 3016.00, '[{\"description\":\"Renda\",\"quantity\":1,\"unit_price\":3000,\"total\":3000},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Despotivo de Tete', 'Ananias Thaunde', '', '2025-11-02 00:00:00'),
(122, 3, 2, 18, 200.00, '[{\"description\":\"Gasolina\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'Bombas de gasolina', 'Pastor Muler', '', '2025-11-01 00:00:00'),
(123, 3, 2, 18, 500.00, '[{\"description\":\"Gasolina\",\"quantity\":1,\"unit_price\":500,\"total\":500}]', 'Bombas de gasolina', 'Pastor Muler', '', '2025-11-03 00:00:00'),
(124, 3, 2, 17, 125.00, '[{\"description\":\"Imprens\\u00e3o de Crachas\",\"quantity\":1,\"unit_price\":125,\"total\":125}]', 'Reprografia RONI', 'Ananias Thaunde', '', '2025-11-03 00:00:00'),
(125, 3, 2, 16, 148.00, '[{\"description\":\"Transporte de chapa\",\"quantity\":1,\"unit_price\":103,\"total\":103},{\"description\":\"Transporte de chapa\",\"quantity\":1,\"unit_price\":45,\"total\":45}]', 'Transporte ', 'Irmão Eduardo', '', '2025-11-01 00:00:00'),
(126, 3, 2, 24, 50.00, '[{\"description\":\"Bolacha e sumo\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'Ceia', 'Pastora', '', '2025-11-03 00:00:00'),
(127, 3, 2, 30, 1641.00, '[{\"description\":\"Outros\",\"quantity\":1,\"unit_price\":970,\"total\":970},{\"description\":\"Celular\",\"quantity\":1,\"unit_price\":660,\"total\":660},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":11,\"total\":11}]', 'Outros', 'Pastores', '', '2025-11-03 00:00:00'),
(128, 3, 2, 10, 1260.00, '[{\"description\":\"Fundo apostolico\",\"quantity\":1,\"unit_price\":1244,\"total\":1244},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Life church', 'Ananias', '', '2025-11-08 00:00:00'),
(129, 3, 2, 18, 500.00, '[{\"description\":\"Gasolina\",\"quantity\":1,\"unit_price\":500,\"total\":500}]', 'Bombas de gasolina', 'Pastores', 'Transporte para pastores', '2025-11-13 00:00:00'),
(130, 3, 2, 30, 200.00, '[{\"description\":\"Outros\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'outros', 'Contigencia', 'Valore nao identificado', '2025-11-14 00:00:00'),
(131, 3, 2, 17, 207.00, '[{\"description\":\"Equipamentos\",\"quantity\":1,\"unit_price\":200,\"total\":200},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":7,\"total\":7}]', 'Lojas na cidade de Tete', 'Achimo', '', '2025-11-15 00:00:00'),
(132, 3, 2, 16, 208.00, '[{\"description\":\"Chapa\",\"quantity\":1,\"unit_price\":208,\"total\":208}]', 'Chapa', 'Eduardo', '', '2025-11-16 00:00:00'),
(133, 3, 2, 16, 180.00, '[{\"description\":\"Chapa\",\"quantity\":1,\"unit_price\":180,\"total\":180}]', 'Chapa', 'Eduardo', '', '2025-11-21 00:00:00'),
(134, 3, 2, 17, 2500.00, '[{\"description\":\"P\\u00falpito\",\"quantity\":1,\"unit_price\":2500,\"total\":2500}]', 'Serrelharia', 'Pastores', '', '2025-11-22 00:00:00'),
(135, 3, 2, 24, 500.00, '[{\"description\":\"Botas\",\"quantity\":1,\"unit_price\":500,\"total\":500}]', 'Aluguer de botas manutenção', 'Irmão Eduardo', 'Botas para subir poste', '2025-11-22 00:00:00'),
(136, 3, 2, 22, 50.00, '[{\"description\":\"Credelec\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'Credelec', 'Ananias', 'Ficamos sem corrente para finalizarmos ensaio', '2025-11-28 00:00:00'),
(137, 3, 2, 17, 400.00, '[{\"description\":\"Pilhas\",\"quantity\":1,\"unit_price\":400,\"total\":400}]', 'Supermercado', 'Elísio', '', '2025-11-29 00:00:00'),
(138, 3, 2, 22, 50.00, '[{\"description\":\"Credelec\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'Credelec', 'Ananias', '', '2025-11-30 00:00:00'),
(139, 3, 2, 16, 150.00, '[{\"description\":\"Tchopela\",\"quantity\":1,\"unit_price\":150,\"total\":150}]', 'Transporte', 'Ananias', 'A irmã Neyde passou mal no culto e teve que se levar para o hospital', '2025-12-02 00:00:00'),
(140, 3, 2, 10, 1124.00, '[{\"description\":\"Fundo apostolico\",\"quantity\":1,\"unit_price\":1108,\"total\":1108},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Igreja Life Church', 'Ananias', '', '2025-12-03 00:00:00'),
(141, 3, 2, 19, 508.00, '[{\"description\":\"Botas\",\"quantity\":1,\"unit_price\":508,\"total\":508}]', 'Botas', 'Eduardo', '', '2025-12-03 00:00:00'),
(142, 3, 2, 16, 200.00, '[{\"description\":\"Transporte\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'Transporte', 'Pastores', '', '2025-12-03 00:00:00'),
(143, 3, 2, 17, 1534.00, '[{\"description\":\"Cortinas\",\"quantity\":1,\"unit_price\":1520,\"total\":1520},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":14,\"total\":14}]', 'Lojas na cidade de Tete', 'Pastores', '', '2025-12-08 00:00:00'),
(144, 3, 2, 17, 2518.00, '[{\"description\":\"Compras\",\"quantity\":1,\"unit_price\":2500,\"total\":2500},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":18,\"total\":18}]', 'Lojas na cidade de Tete', 'Pastores', '', '2025-12-10 00:00:00'),
(145, 3, 2, 17, 400.00, '[{\"description\":\"Capulanas\",\"quantity\":1,\"unit_price\":400,\"total\":400}]', 'Lojas na cidade de Tete', 'Pastores', '', '2025-12-22 00:00:00'),
(146, 3, 2, 17, 310.00, '[{\"description\":\"Lampadas\",\"quantity\":1,\"unit_price\":310,\"total\":310}]', 'Cambinde', 'Pastores', '', '2025-12-22 00:00:00'),
(147, 3, 2, 17, 3300.00, '[{\"description\":\"Equipamento Coral\",\"quantity\":1,\"unit_price\":3300,\"total\":3300}]', 'Lojas na cidade de Tete', 'Elísio', '', '2025-12-22 00:00:00'),
(148, 3, 2, 17, 800.00, '[{\"description\":\"Fio de corrente eletrica\",\"quantity\":1,\"unit_price\":800,\"total\":800}]', 'Cambinde', 'Pastores', '', '2025-12-22 00:00:00'),
(149, 3, 2, 17, 510.00, '[{\"description\":\"Desconhecido\",\"quantity\":1,\"unit_price\":510,\"total\":510}]', 'Lojas na cidade de Tete', 'Pastores', '', '2025-12-22 00:00:00'),
(150, 3, 2, 24, 3000.00, '[{\"description\":\"Bateria\",\"quantity\":1,\"unit_price\":3000,\"total\":3000}]', 'Aluguer de bateria', 'Irmao Elisio', '', '2025-12-24 00:00:00'),
(151, 3, 2, 24, 3000.00, '[{\"description\":\"Bateria\",\"quantity\":1,\"unit_price\":3000,\"total\":3000}]', 'Aluguer de bateria', 'Irmao Elisio', '', '2025-12-24 00:00:00'),
(152, 3, 2, 19, 840.00, '[{\"description\":\"Componentes eletricos\",\"quantity\":1,\"unit_price\":840,\"total\":840}]', 'Cambinde', 'Irmao Eduardo', '', '2025-12-24 00:00:00'),
(153, 3, 2, 22, 50.00, '[{\"description\":\"Credelec\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'Credelec', 'Ananias', '', '2025-12-24 00:00:00'),
(154, 3, 2, 22, 50.00, '[{\"description\":\"Credelec\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'Credelec', 'Ananias', '', '2025-12-31 00:00:00'),
(155, 3, 2, 22, 50.00, '[{\"description\":\"Credelec\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'Credelec', 'Ananias', '', '2025-12-31 00:00:00'),
(156, 7, 2, 25, 1316.00, '[{\"description\":\"Frango\",\"quantity\":1,\"unit_price\":380,\"total\":380},{\"description\":\"Frango\",\"quantity\":1,\"unit_price\":270,\"total\":270},{\"description\":\"Refresco\",\"quantity\":1,\"unit_price\":89,\"total\":89},{\"description\":\"Agua\",\"quantity\":1,\"unit_price\":60,\"total\":60},{\"description\":\"Sumo\",\"quantity\":3,\"unit_price\":65,\"total\":195},{\"description\":\"Plastico\",\"quantity\":1,\"unit_price\":5,\"total\":5},{\"description\":\"Limao \",\"quantity\":1,\"unit_price\":20,\"total\":20},{\"description\":\"Cebola\",\"quantity\":1,\"unit_price\":5,\"total\":5},{\"description\":\"Pedra de gelo\",\"quantity\":1,\"unit_price\":25,\"total\":25},{\"description\":\"Farinha\",\"quantity\":2,\"unit_price\":59,\"total\":118},{\"description\":\"Carv\\u00e3o\",\"quantity\":1,\"unit_price\":40,\"total\":40},{\"description\":\"Valor n\\u00e3o usado\",\"quantity\":1,\"unit_price\":109,\"total\":109}]', 'Mercados', 'Família Muler', '', '2026-01-02 00:00:00'),
(157, 7, 2, 10, 1476.00, '[{\"description\":\" Fundo apostolico\",\"quantity\":1,\"unit_price\":1460,\"total\":1460},{\"description\":\"Taxa\",\"quantity\":1,\"unit_price\":16,\"total\":16}]', 'Fundo apostolico', 'Ananias Thaunde', '', '2026-01-26 00:00:00'),
(158, 7, 2, 15, 3016.00, '[{\"description\":\"Pagamento de renda\",\"quantity\":1,\"unit_price\":3016,\"total\":3016}]', 'Grupo desportivo de Tete', 'Ananias Thaunde', '', '2026-01-26 00:00:00'),
(159, 7, 2, 22, 50.00, '[{\"description\":\"Credelec\",\"quantity\":1,\"unit_price\":50,\"total\":50}]', 'EDM', 'Ananias Thaunde', '', '2026-01-26 00:00:00'),
(160, 7, 46, 16, 200.00, '[{\"description\":\"Transprte\",\"quantity\":1,\"unit_price\":200,\"total\":200}]', 'Life church Chimoio', 'Ananias', '', '2026-01-31 00:00:00'),
(161, 7, 46, 16, 508.00, '[{\"description\":\"Compra de camara de ar e gasolina\",\"quantity\":1,\"unit_price\":508,\"total\":508}]', 'Tete', 'Pastor Muler', 'Valor enviado para transporte e reuniao familiar, familia Silvinio e Pires', '2026-01-29 00:00:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `members`
--

CREATE TABLE `members` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `church_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `members`
--

INSERT INTO `members` (`id`, `user_id`, `church_id`) VALUES
(12, 29, 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `registo_atividades`
--

CREATE TABLE `registo_atividades` (
  `id` int NOT NULL,
  `celula_id` int NOT NULL,
  `lider_id` int NOT NULL,
  `data_registo` date NOT NULL,
  `tipo_registo` enum('celula','culto') COLLATE utf8mb4_general_ci NOT NULL,
  `participacoes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `visitantes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `candidatos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `eventos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Despejando dados para a tabela `registo_atividades`
--

INSERT INTO `registo_atividades` (`id`, `celula_id`, `lider_id`, `data_registo`, `tipo_registo`, `participacoes_json`, `visitantes_json`, `candidatos_json`, `eventos_json`, `created_at`) VALUES
(23, 6, 45, '2026-01-03', 'celula', '[{\"member_id\":\"47\",\"presente\":false,\"ausente\":true,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":false,\"ausente\":true,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:19:00'),
(24, 6, 45, '2026-01-10', 'celula', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[{\"nome\":\"Mateus \",\"contacto\":\"\",\"outros\":\"\"}]', '[]', '{\"oracao\":\"2026-01-10\"}', '2026-01-31 12:19:38'),
(25, 5, 46, '2026-01-03', 'celula', '[{\"member_id\":\"46\",\"presente\":false,\"ausente\":true,\"motivo\":\"N\\u00e3o esteve em casa por conta do trabalho\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"49\",\"presente\":false,\"ausente\":true,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"44\",\"presente\":false,\"ausente\":true,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"48\",\"presente\":false,\"ausente\":true,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"50\",\"presente\":false,\"ausente\":true,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:19:40'),
(26, 6, 45, '2026-01-17', 'celula', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '{\"oracao\":\"2026-01-17\"}', '2026-01-31 12:20:04'),
(27, 5, 46, '2026-01-10', 'celula', '[{\"member_id\":\"46\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"49\",\"presente\":false,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"44\",\"presente\":false,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"48\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"50\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:20:12'),
(28, 6, 45, '2026-01-24', 'celula', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '{\"oracao\":\"2026-01-24\"}', '2026-01-31 12:20:38'),
(30, 5, 46, '2026-01-17', 'celula', '[{\"member_id\":\"46\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"49\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"44\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"48\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"50\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:20:54'),
(32, 5, 46, '2026-01-24', 'celula', '[{\"member_id\":\"46\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"49\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"44\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"48\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"50\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[{\"nome\":\"Delfina\",\"contacto\":\"\",\"outros\":\"\"},{\"nome\":\"Fabi\\u00e3o\",\"contacto\":\"\",\"outros\":\"\"},{\"nome\":\"Bebe Natan\",\"contacto\":\"\",\"outros\":\"\"}]', '[]', '{\"oracao\":\"2026-01-24\"}', '2026-01-31 12:22:07'),
(34, 6, 45, '2026-01-03', 'culto', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:22:27'),
(35, 6, 45, '2026-01-10', 'celula', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:22:46'),
(36, 6, 45, '2026-01-10', 'celula', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:22:51'),
(37, 6, 45, '2026-01-17', 'celula', '[{\"member_id\":\"47\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"45\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false}]', '[]', '[]', '[]', '2026-01-31 12:23:09'),
(38, 5, 46, '2026-01-31', 'celula', '[{\"member_id\":\"46\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"49\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"44\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"48\",\"presente\":true,\"ausente\":false,\"motivo\":\"\",\"discipulado\":false,\"pastoral\":false},{\"member_id\":\"50\",\"presente\":false,\"ausente\":true,\"motivo\":\"Trabalho\",\"discipulado\":false,\"pastoral\":false}]', '[{\"nome\":\"Feliberto \",\"contacto\":\"Em casa de irm\\u00e3o Ananias\",\"outros\":\"\"}]', '[]', '{\"oracao\":\"2026-01-31\"}', '2026-01-31 15:02:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `service_reports`
--

CREATE TABLE `service_reports` (
  `id` int NOT NULL,
  `church_id` int NOT NULL,
  `user_id` int NOT NULL,
  `service_date` date NOT NULL,
  `theme` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adults_members` int DEFAULT '0',
  `adults_visitors` int DEFAULT '0',
  `children_members` int DEFAULT '0',
  `children_visitors` int DEFAULT '0',
  `total_attendance` int DEFAULT '0',
  `adult_saved` int DEFAULT '0',
  `child_saved` int DEFAULT '0',
  `offering` decimal(10,2) DEFAULT '0.00',
  `special_offering` decimal(10,2) DEFAULT '0.00',
  `total_offering` decimal(10,2) DEFAULT '0.00',
  `comments` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `service_reports`
--

INSERT INTO `service_reports` (`id`, `church_id`, `user_id`, `service_date`, `theme`, `adults_members`, `adults_visitors`, `children_members`, `children_visitors`, `total_attendance`, `adult_saved`, `child_saved`, `offering`, `special_offering`, `total_offering`, `comments`, `created_at`) VALUES
(31, 3, 2, '2025-01-01', 'Saldo Inicial de 2023', 0, 0, 0, 0, 0, 0, 0, 411.40, 0.00, 411.40, NULL, '2025-07-10 17:17:42'),
(32, 3, 2, '2025-04-27', NULL, 35, 4, 14, 2, 55, 0, 0, 285.00, 0.00, 3805.00, NULL, '2025-07-10 17:17:42'),
(33, 3, 2, '2025-05-04', NULL, 33, 2, 8, 4, 47, 0, 0, 1223.00, 0.00, 4773.00, NULL, '2025-07-10 17:17:42'),
(34, 3, 2, '2025-05-11', NULL, 38, 2, 10, 3, 53, 0, 0, 152.00, 0.00, 5167.00, NULL, '2025-07-10 17:17:42'),
(35, 3, 2, '2025-05-18', NULL, 43, 1, 13, 0, 57, 0, 0, 72.00, 0.00, 462.00, NULL, '2025-07-10 17:17:42'),
(36, 3, 2, '2025-05-25', NULL, 40, 3, 10, 0, 53, 0, 0, 168.00, 0.00, 3218.00, NULL, '2025-07-10 17:17:42'),
(37, 3, 2, '2025-06-01', NULL, 32, 1, 16, 0, 49, 0, 0, 255.00, 100.00, 7695.00, NULL, '2025-07-10 17:17:42'),
(38, 3, 2, '2025-06-08', NULL, 25, 3, 12, 1, 41, 0, 0, 110.00, 0.00, 410.00, NULL, '2025-07-10 17:17:42'),
(39, 3, 2, '2025-06-15', NULL, 30, 2, 5, 0, 37, 0, 0, 135.00, 100.00, 635.00, NULL, '2025-07-10 17:17:42'),
(40, 3, 2, '2025-06-22', NULL, 36, 4, 6, 0, 46, 0, 0, 1118.00, 0.00, 4687.00, NULL, '2025-07-10 17:17:42'),
(41, 3, 2, '2025-06-29', NULL, 34, 2, 9, 4, 49, 0, 0, 200.00, 0.00, 8810.00, NULL, '2025-07-10 17:17:42'),
(42, 3, 2, '2025-07-06', 'Princípios de santidade e pureza', 31, 2, 10, 2, 45, 0, 0, 137.00, 0.00, 137.00, '', '2025-07-10 17:17:42'),
(43, 3, 2, '2025-07-13', 'Princípios de integridade e honestidade', 35, 9, 9, 0, 53, 0, 0, 482.00, 0.00, 2622.00, '', '2025-07-13 15:26:23'),
(44, 3, 2, '2025-07-20', 'Princípios de honra', 33, 2, 4, 0, 39, 0, 0, 156.00, 0.00, 156.00, '', '2025-07-21 07:55:13'),
(45, 3, 2, '2025-07-29', 'Princípio de generosidade', 29, 3, 12, 2, 46, 0, 0, 128.00, 0.00, 4798.00, '', '2025-07-29 16:01:23'),
(47, 4, 42, '2025-07-27', 'Princípio de honra ', 26, 2, 13, 3, 44, 0, 0, 125.00, 25550.00, 32658.00, 'Culto pregado por irmão Ramos, pastor estava na conferência, correu de forma normal mas havia muito sol, e falta de cobertura atrapalhou', '2025-08-02 12:40:46'),
(48, 4, 42, '2025-08-03', 'Principio da Serventia', 29, 1, 14, 0, 44, 0, 0, 257.00, 4528.00, 7135.00, 'Culto pregado pelo Irmao Ramos, foi celebrado aniversao da Aila, houve mau tempo', '2025-08-03 10:43:41'),
(49, 3, 2, '2025-08-03', 'Princípios de mordomia', 35, 3, 12, 0, 50, 0, 0, 262.00, 0.00, 6591.00, '', '2025-08-03 15:23:49'),
(50, 6, 36, '2025-08-03', 'Princípio de Generosidade', 95, 2, 29, 1, 127, 0, 0, 211.00, 0.00, 8293.00, 'Foi um culto abençoado', '2025-08-03 17:34:16'),
(51, 6, 36, '2025-08-01', 'Significância (O valor da criação)', 30, 1, 5, 0, 36, 0, 0, 150.00, 5973.00, 6123.00, '', '2025-08-03 17:37:42'),
(53, 3, 2, '2025-08-10', 'Provação e tentação', 34, 2, 8, 0, 44, 0, 0, 117.00, 0.00, 3717.00, '', '2025-08-08 10:36:39'),
(54, 4, 42, '2025-08-10', 'Princípio da Mordomia ', 29, 2, 18, 0, 49, 0, 0, 261.00, 1100.00, 1661.00, 'Culto pregado por Carlitos Rafael, dia de muito sol em Chimoio ', '2025-08-10 10:33:03'),
(56, 4, 42, '2025-08-17', 'Perseverança ', 30, 0, 13, 0, 43, 0, 0, 272.00, 0.00, 272.00, '', '2025-08-17 17:54:55'),
(57, 3, 2, '2025-08-17', 'Uma vida de compromisso', 45, 2, 10, 1, 58, 0, 0, 257.00, 0.00, 702.00, '', '2025-08-18 12:59:09'),
(59, 3, 2, '2025-08-24', 'O sentido da verdadeira fé', 44, 4, 9, 0, 57, 0, 0, 259.00, 0.00, 559.00, '', '2025-09-04 14:08:53'),
(60, 3, 2, '2025-09-04', 'Fé incoruptivel', 41, 0, 8, 1, 50, 0, 0, 157.00, 0.00, 1007.00, '', '2025-09-04 14:14:47'),
(61, 4, 42, '2025-09-07', 'Entre a fé a razão ', 25, 1, 14, 0, 40, 0, 0, 175.00, 0.00, 4455.00, '', '2025-09-07 11:41:45'),
(62, 3, 2, '2025-09-07', 'Entre a fé e a razão', 41, 2, 15, 0, 58, 0, 0, 150.00, 0.00, 6899.00, '', '2025-09-08 21:04:30'),
(63, 4, 42, '2025-09-14', 'A fe além das circunstâncias ', 30, 0, 18, 0, 48, 0, 0, 470.00, 0.00, 470.00, '', '2025-09-14 12:32:43'),
(64, 4, 42, '2025-09-14', 'A fé Além das circunstâncias ', 30, 0, 18, 0, 48, 0, 0, 470.00, 0.00, 470.00, '', '2025-09-14 12:33:40'),
(65, 3, 2, '2025-09-14', 'Fé além ', 30, 0, 6, 0, 36, 0, 0, 72.00, 0.00, 747.00, '', '2025-09-15 09:25:52'),
(66, 4, 42, '2025-09-21', 'Fé e obras ', 37, 1, 23, 0, 61, 0, 0, 170.00, 0.00, 4882.00, '', '2025-09-21 10:21:22'),
(67, 3, 2, '2025-09-24', 'Obras produzidas pela fé', 42, 2, 19, 0, 63, 0, 0, 174.00, 0.00, 724.00, '', '2025-09-24 19:09:26'),
(68, 4, 42, '2025-09-28', 'fé testada nos leva a perseverança', 18, 2, 14, 0, 34, 0, 0, 3756.00, 2510.00, 6266.00, '', '2025-09-28 10:20:06'),
(69, 3, 2, '2025-09-29', 'Fé provada que nos faz perseverar ', 42, 1, 8, 0, 51, 0, 0, 201.00, 4000.00, 4571.00, '', '2025-09-29 21:36:05'),
(70, 3, 2, '2025-10-05', 'O poder da fé', 35, 2, 6, 0, 43, 0, 0, 144.00, 0.00, 7779.00, '', '2025-09-30 16:53:47'),
(71, 3, 2, '2025-10-04', ' ', 0, 0, 0, 0, 0, 0, 0, 19.00, 0.00, 19.00, '', '2025-10-04 07:36:24'),
(72, 4, 42, '2025-10-06', 'PODER DA FÉ ', 22, 0, 15, 0, 37, 0, 0, 95.00, 0.00, 8740.00, '', '2025-10-06 06:16:21'),
(73, 3, 2, '2025-10-12', 'Justificados pela fé', 33, 0, 6, 0, 39, 0, 0, 246.00, 0.00, 596.00, '', '2025-10-07 17:14:57'),
(74, 4, 42, '2025-10-12', 'Justificados pela fé ', 31, 0, 28, 0, 59, 0, 0, 205.00, 0.00, 3547.00, '', '2025-10-12 11:05:34'),
(77, 3, 2, '2025-10-14', ' ', 0, 0, 0, 0, 0, 0, 0, 0.00, 0.00, 20.00, '', '2025-10-14 16:51:19'),
(78, 4, 42, '2025-10-19', 'Plantados para produzir bom fruto ', 22, 0, 21, 0, 43, 0, 0, 255.00, 0.00, 255.00, '', '2025-10-19 15:25:57'),
(79, 3, 2, '2025-10-21', 'Plantados para produzir bons frutos ', 41, 1, 7, 1, 50, 0, 0, 199.00, 70.00, 269.00, '', '2025-10-21 12:36:50'),
(80, 4, 42, '2025-10-26', 'O Deus que faz o impossivel acontecer', 26, 2, 30, 0, 58, 0, 0, 385.00, 0.00, 3085.00, '', '2025-10-26 13:56:03'),
(81, 3, 2, '2025-11-26', 'Andando em espírito', 37, 1, 11, 1, 50, 0, 0, 262.00, 0.00, 3797.00, '', '2025-11-03 12:24:34'),
(82, 3, 2, '2025-11-02', 'Ser devoto a Deus', 43, 0, 14, 0, 57, 0, 0, 163.00, 0.00, 263.00, '', '2025-11-03 12:25:46'),
(83, 3, 2, '2025-11-09', 'Servir a Deus com motivação ', 31, 1, 8, 1, 41, 0, 0, 111.00, 0.00, 4031.00, '', '2025-11-09 09:44:12'),
(84, 4, 42, '2025-11-09', 'Motivação do coração', 27, 0, 12, 0, 39, 0, 0, 432.00, 0.00, 5852.00, '', '2025-11-09 10:26:04'),
(85, 4, 42, '2025-11-16', 'Fidelidade no pouco e no muito ', 26, 0, 13, 0, 39, 0, 0, 300.00, 0.00, 300.00, '', '2025-11-16 11:07:53'),
(86, 3, 2, '2025-11-16', 'Fidelidade no pouco e no muito', 39, 2, 13, 0, 54, 0, 0, 86.00, 0.00, 186.00, '', '2025-11-16 16:37:17'),
(87, 4, 42, '2025-11-23', 'Um coração ensinável ', 25, 1, 14, 0, 40, 0, 0, 304.00, 0.00, 3004.00, '', '2025-11-23 10:34:43'),
(88, 3, 2, '2025-11-23', 'Um coração ensinável', 36, 2, 7, 0, 45, 0, 0, 244.00, 0.00, 971.00, '', '2025-11-24 17:34:49'),
(89, 4, 42, '2025-12-01', 'Consagração ', 12, 0, 7, 0, 19, 0, 0, 3275.00, 3000.00, 6275.00, '', '2025-12-01 05:55:37'),
(90, 3, 2, '2025-11-30', 'Consagração', 21, 1, 3, 0, 25, 0, 0, 70.00, 0.00, 5630.00, '', '2025-12-03 12:12:29'),
(91, 4, 42, '2025-12-07', 'Servindo a Deus com excelência', 29, 3, 17, 0, 49, 0, 0, 160.00, 1475.00, 4999.00, '', '2025-12-07 10:47:11'),
(92, 4, 42, '2025-12-01', '', 12, 0, 7, 0, 19, 0, 0, 3275.00, 3000.00, 6275.00, '', '2025-12-07 10:50:42'),
(93, 4, 42, '2025-12-14', 'Carácter e Integridade ', 28, 2, 15, 0, 45, 0, 0, 230.00, 0.00, 230.00, '', '2025-12-15 04:42:57'),
(94, 4, 42, '2025-12-14', 'Carácter e Integridade ', 28, 2, 15, 0, 45, 0, 0, 230.00, 2000.00, 2230.00, '', '2025-12-15 04:46:18'),
(95, 4, 42, '2025-12-21', 'Comunhão - Não se afaste da comunhão com outros irmãos na fé', 0, 0, 0, 0, 0, 0, 0, 0.00, 0.00, 0.00, '', '2025-12-21 10:15:25'),
(96, 4, 42, '2025-12-21', 'Comunhão - Não se afaste da comunhão com outros irmãos na fé', 13, 1, 4, 0, 18, 0, 0, 290.00, 3000.00, 5990.00, '', '2025-12-21 10:16:22'),
(97, 3, 2, '2025-12-14', 'Carácter e integridade ', 32, 2, 11, 1, 46, 0, 0, 333.00, 0.00, 733.00, '', '2025-12-22 14:59:28'),
(98, 3, 2, '2025-12-21', 'Servir a Deus em comunidade', 33, 0, 12, 1, 46, 0, 0, 509.00, 0.00, 4819.00, '', '2025-12-22 15:07:01'),
(99, 3, 2, '2025-12-07', 'Deligencia e perseverança ', 37, 1, 11, 0, 49, 0, 0, 142.00, 0.00, 5242.00, '', '2025-12-22 15:37:34'),
(100, 4, 42, '2025-12-28', 'Acções de graça', 12, 0, 5, 0, 17, 0, 0, 44.00, 0.00, 6958.00, '', '2025-12-29 08:06:33'),
(102, 3, 2, '2025-12-28', 'Ação de graças', 35, 1, 2, 1, 39, 0, 0, 120.00, 0.00, 3809.00, '', '2026-01-02 08:00:08'),
(103, 4, 42, '2026-01-04', 'Dar o nosso melhor ', 16, 3, 4, 0, 23, 0, 0, 135.00, 0.00, 1610.00, '', '2026-01-04 10:01:40'),
(104, 4, 42, '2026-01-11', 'Promessas de Deus', 15, 0, 0, 0, 15, 0, 0, 0.00, 0.00, 0.00, 'Culto virtual, devido ao mau tempo na cidade, não haviam condições para cultuar ao relento ', '2026-01-11 19:12:23'),
(105, 3, 2, '2025-08-31', '', 41, 2, 8, 1, 52, 0, 0, 157.00, 0.00, 1007.00, '', '2026-01-17 08:54:21'),
(106, 3, 2, '2026-01-18', 'Deligencia e perseverança ', 26, 1, 5, 0, 32, 0, 0, 154.00, 0.00, 974.00, '', '2026-01-18 09:18:06'),
(107, 4, 42, '2026-01-18', 'Fazei tudo quanto ele vos disser', 20, 1, 13, 0, 34, 0, 0, 135.00, 0.00, 135.00, '', '2026-01-18 10:06:57'),
(108, 4, 42, '2026-01-25', 'Quando sou fraco então sou forte ', 22, 2, 17, 0, 41, 0, 0, 726.00, 0.00, 8626.00, '', '2026-01-25 10:35:25'),
(109, 3, 2, '2026-01-25', 'Quando sou fraco, então sou forte', 38, 2, 8, 0, 48, 0, 0, 137.00, 0.00, 2207.00, '', '2026-01-26 09:07:01'),
(110, 7, 2, '2025-01-18', 'Portanto prossigo para o alvo que é Cristo', 35, 0, 10, 1, 46, 0, 0, 220.00, 0.00, 1070.00, '', '2026-01-26 17:37:14'),
(111, 7, 2, '2025-01-25', 'Quando sou fraco, então sou forte', 38, 2, 8, 0, 48, 0, 0, 137.00, 0.00, 5667.00, '', '2026-01-26 17:42:38'),
(112, 7, 2, '2025-01-04', 'Andando em espírito Gálatas (5:16-26)', 33, 1, 6, 0, 40, 0, 0, 102.00, 0.00, 102.00, '', '2026-01-26 17:47:16'),
(113, 7, 2, '2025-01-18', 'Fazei tudo quanto ele vos disser', 32, 1, 5, 0, 38, 0, 0, 154.00, 0.00, 974.00, '', '2026-01-26 17:56:35'),
(114, 7, 46, '2026-02-01', 'Vida em espírito', 41, 2, 12, 0, 55, 0, 0, 184.00, 0.00, 184.00, '', '2026-02-01 10:24:07'),
(115, 4, 42, '2026-02-01', 'A vida no espirito ', 19, 1, 15, 0, 35, 0, 0, 2110.00, 0.00, 2510.00, '', '2026-02-01 13:30:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tithes`
--

CREATE TABLE `tithes` (
  `id` int NOT NULL,
  `report_id` int NOT NULL,
  `church_id` int NOT NULL,
  `tither_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tithes`
--

INSERT INTO `tithes` (`id`, `report_id`, `church_id`, `tither_name`, `amount`) VALUES
(25, 32, 3, 'Dízimos Abril Sem 4', 3520.00),
(26, 33, 3, 'Dízimos Maio Sem 1', 3550.00),
(27, 34, 3, 'Dízimos Maio Sem 2', 5015.00),
(28, 35, 3, 'Dízimos Maio Sem 3', 390.00),
(29, 36, 3, 'Dízimos Maio Sem 4', 3050.00),
(30, 37, 3, 'Dízimos Junho Sem 1', 7340.00),
(31, 38, 3, 'Dízimos Junho Sem 2', 300.00),
(32, 39, 3, 'Dízimos Junho Sem 3', 400.00),
(33, 40, 3, 'Dízimos Junho Sem 4', 3569.00),
(34, 41, 3, 'Dízimos Junho Sem 5', 8610.00),
(36, 43, 3, ' ', 50.00),
(37, 43, 3, ' ', 50.00),
(38, 43, 3, ' ', 1640.00),
(39, 43, 3, ' ', 100.00),
(40, 43, 3, ' ', 300.00),
(42, 45, 3, ' ', 100.00),
(43, 45, 3, ' ', 3460.00),
(44, 45, 3, ' ', 1110.00),
(47, 47, 4, ' ', 4283.00),
(48, 47, 4, ' ', 2700.00),
(49, 48, 4, ' ', 2350.00),
(54, 50, 6, 'Anónimo ', 8082.00),
(57, 49, 3, ' ', 3469.00),
(58, 49, 3, ' ', 2390.00),
(59, 49, 3, ' ', 370.00),
(60, 49, 3, ' ', 100.00),
(63, 54, 4, ' ', 300.00),
(67, 53, 3, ' ', 1500.00),
(68, 53, 3, ' ', 2000.00),
(69, 53, 3, ' ', 100.00),
(75, 57, 3, ' ', 345.00),
(76, 57, 3, ' ', 100.00),
(77, 59, 3, ' ', 100.00),
(78, 59, 3, ' ', 200.00),
(79, 60, 3, ' ', 100.00),
(80, 60, 3, ' ', 150.00),
(81, 60, 3, ' ', 500.00),
(82, 60, 3, ' ', 100.00),
(83, 61, 4, ' ', 4280.00),
(84, 62, 3, ' ', 800.00),
(85, 62, 3, ' ', 2000.00),
(86, 62, 3, ' ', 200.00),
(87, 62, 3, ' ', 280.00),
(88, 62, 3, ' ', 3469.00),
(89, 65, 3, ' ', 375.00),
(90, 65, 3, ' ', 100.00),
(91, 65, 3, ' ', 200.00),
(92, 66, 4, ' ', 1382.00),
(93, 66, 4, ' ', 2700.00),
(94, 66, 4, ' ', 630.00),
(104, 67, 3, ' ', 460.00),
(105, 67, 3, ' ', 90.00),
(109, 69, 3, ' ', 270.00),
(110, 69, 3, ' ', 100.00),
(139, 70, 3, ' ', 375.00),
(140, 70, 3, ' ', 3460.00),
(141, 70, 3, ' ', 1100.00),
(142, 70, 3, ' ', 50.00),
(143, 70, 3, ' ', 2550.00),
(144, 70, 3, ' ', 100.00),
(145, 72, 4, ' ', 4270.00),
(146, 72, 4, ' ', 4375.00),
(154, 73, 3, ' ', 250.00),
(155, 73, 3, ' ', 100.00),
(156, 74, 4, ' ', 1402.00),
(157, 74, 4, ' ', 1940.00),
(160, 77, 3, ' ', 20.00),
(161, 80, 4, ' ', 2700.00),
(162, 81, 3, ' ', 25.00),
(163, 81, 3, ' ', 50.00),
(164, 81, 3, ' ', 3460.00),
(165, 82, 3, ' ', 100.00),
(166, 83, 3, ' ', 50.00),
(167, 83, 3, ' ', 200.00),
(168, 83, 3, ' ', 3670.00),
(169, 84, 4, ' ', 3000.00),
(170, 84, 4, ' ', 2420.00),
(171, 86, 3, ' ', 100.00),
(172, 87, 4, ' ', 2700.00),
(173, 88, 3, ' ', 60.00),
(174, 88, 3, ' ', 200.00),
(175, 88, 3, ' ', 467.00),
(176, 90, 3, ' ', 25.00),
(177, 90, 3, ' ', 75.00),
(178, 90, 3, ' ', 3460.00),
(179, 90, 3, ' ', 2000.00),
(181, 91, 4, ' ', 2344.00),
(182, 91, 4, ' ', 1020.00),
(183, 96, 4, ' ', 2700.00),
(184, 97, 3, ' ', 400.00),
(188, 99, 3, ' ', 3470.00),
(189, 99, 3, ' ', 1630.00),
(190, 98, 3, ' ', 850.00),
(191, 98, 3, ' ', 3460.00),
(194, 100, 4, ' ', 2714.00),
(195, 100, 4, ' ', 4200.00),
(199, 103, 4, ' ', 1475.00),
(200, 102, 3, ' ', 220.00),
(201, 102, 3, ' ', 3469.00),
(202, 105, 3, ' ', 100.00),
(203, 105, 3, ' ', 150.00),
(204, 105, 3, ' ', 500.00),
(205, 105, 3, ' ', 100.00),
(206, 106, 3, ' ', 820.00),
(207, 108, 4, ' ', 7900.00),
(210, 109, 3, ' ', 2000.00),
(211, 109, 3, ' ', 70.00),
(218, 110, 7, ' ', 100.00),
(219, 110, 7, ' ', 100.00),
(220, 110, 7, ' ', 600.00),
(221, 110, 7, ' ', 50.00),
(222, 113, 7, ' ', 820.00),
(223, 111, 7, ' ', 2000.00),
(224, 111, 7, ' ', 70.00),
(225, 111, 7, ' ', 3460.00),
(227, 115, 4, ' ', 400.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idade` int DEFAULT NULL,
  `batizado` enum('sim','nao') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'nao',
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'membro',
  `church_id` int DEFAULT NULL,
  `celula_id` int DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `idade`, `batizado`, `city`, `role`, `church_id`, `celula_id`, `is_approved`, `is_verified`, `verification_token`, `created_at`) VALUES
(1, 'Master Admin', 'admin@lifechurch.com', '$2y$10$iRn5b403Sj.d5O6v0s5X8e.VLp2QsoiFUPRzC0TGAh2n18Yg0Q9eW', NULL, NULL, 'nao', NULL, 'lider', 3, NULL, 1, 1, NULL, '2025-06-27 17:44:54'),
(2, 'Ananias Manuel Joaquim Thaunde', 'hananiasthaunde@gmail.com', '$2y$10$DFaMc3XwIiG5TLrETXMeJOHsVVgzjpehu6flzS5LtKpjKkpDqzSCS', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'master_admin', 7, NULL, 1, 1, NULL, '2025-06-27 17:52:35'),
(3, 'Ananias Manuel Joaquim Thaunde', 'hananiasthaunde1@gmail.com', '$2y$10$P0URAbYb4pq9QfGVVhg3mOnrsr7EQ.wjRlNHm9h8vjobW48x6GUN6', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'lider', 3, NULL, 1, 0, NULL, '2025-06-27 17:58:22'),
(4, 'Ananias Manuel Joaquim Thaunde', 'hananiasthaunde2@gmail.com', '$2y$10$UipufYdvMb6zeFesFvNWjuUoKLrFOkz4/DTqXKJ1LUQa9.ADoRr3O', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'pastor', 3, NULL, 0, 0, NULL, '2025-06-27 18:03:32'),
(5, 'Hanna', 'hananiasthaunde121@gmail.com', '$2y$10$0RZg5bezx/WL.odPRwzza.aQTXPKw4nUvx3FWdAR9i0a5x4ufPqX2', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-06-27 18:43:25'),
(6, 'Ananias Manuel Joaquim Thaunde', 'hananiasth@gmail.com', '$2y$10$Dxq3LiqRcRVowrRs23zLa.9T315l2hyqQcGo3b/KI6I8XKzGbe/rC', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', NULL, NULL, 0, 0, NULL, '2025-06-27 18:43:36'),
(7, 'admin', 'admin@gmail.com', '$2y$10$DK0UvqlBljq47K2z/N9SJueprjGax1Y6c.r.z9dn1rBDW7sRwhwEm', '+258 0842163212', NULL, 'nao', 'Tete', 'membro', 3, NULL, 1, 0, NULL, '2025-06-27 18:43:45'),
(8, 'errer', 're@gmail.com', '$2y$10$8Klwk5G5OwG.duKJa4U78uI6InIu4aLVceBJ/644QPr/yGS9Rm7oO', 'rere', NULL, 'nao', 'erer', 'membro', 3, NULL, 0, 0, NULL, '2025-06-27 18:44:12'),
(9, 'Ananias Manuel Joaquim Thaunde', 'hananiasth43334@gmail.com', '$2y$10$jADqDDJZPLpedo7jP/fAZ.TWbHnh/TpJOIe77.0G8VQR5/Q6QkDGK', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 1, 0, NULL, '2025-06-27 18:44:23'),
(10, 'Ananias Manuel Joaquim Thaunde', 'hananiasthaun23232e@gmail.com', '$2y$10$tyyzaK.uheuSyPUsYrnOF.3BrlIbkYH4uH8vHird9ophD/SrK56vm', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 1, 0, NULL, '2025-06-27 18:44:42'),
(11, 'Ananias Manuel Joaquim Thaunde', 'hananiasthau2n23232e@gmail.com', '$2y$10$PCpgs6Y6NCJKsvaGg2tT9eZuzzKDGI5VVBA1DVoAM9ITDQ3lMeHY.', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-06-27 18:44:54'),
(12, 'fef', 'hananiasthau2n2wee3232e@gmail.com', '$2y$10$.2xjzIgQcVdWJvoth5RDc.kYYi8WSSQTGDJaePSodwl6ArODAnZsi', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-06-27 18:45:12'),
(13, 'fef', 'hananiasthau2en2wee3232e@gmail.com', '$2y$10$KjKHhjwmobNoDWQzUiOP0OnfCNEJzP2dfrcMi6NCU3OHneT/btqPu', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-06-27 18:45:18'),
(14, 'amj', 'ananiasthaunde2@gmail.com', '$2y$10$AZJn1MOL82jvR0LnGeRTZOXQI.BAcuSeeGQVo9H6PxIkloAJYUlbC', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'master_admin', 3, NULL, 0, 0, NULL, '2025-06-27 19:08:50'),
(15, 'Ananias Manuel Joaquim Thaunde', 'thth@gmail.com', '$2y$10$JKoBE5RtN1zo.dmge.URWOU.Z0I09d/kFnS00Sejp2fEhu.oqe.B.', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'lider', 3, NULL, 0, 0, NULL, '2025-06-27 19:50:54'),
(16, 'celula', 'celula@gmail.com', '$2y$10$HH2EungzNMtRCSR.N/9MOOKzJTZYGs3HjA4x1Nj9g8FyVzpxDW1dy', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'lider', 3, NULL, 1, 0, NULL, '2025-06-27 21:39:35'),
(17, 'lider@gmail.com', 'lider@gmail.com', '$2y$10$6b3hAiZT3qjnIPn6Ge18N.vZCb1Dnnv6KWwdixuZVABJIVmYpDo0y', '879299196', NULL, 'nao', 'teste', 'lider', 3, NULL, 1, 0, NULL, '2025-06-28 00:19:07'),
(18, 'teste', 'krishnonaskar6@gmail.com', '$2y$10$RqX8B.G9hQonXzihKMNipOJo8fSvAm4qRAiej0YKtj7Wmg/z2itfO', '879299196', NULL, 'sim', '', 'membro', 3, 4, 1, 0, NULL, '2025-06-28 00:26:06'),
(20, 'teste', 'hananiasthaunde445445454@gmail.com', '$2y$10$fs2LZhSnUKb6p1PzFyxBYuVZWX4ESOjsYkWEE2zHeo5QOCzl6AQ1u', '+258 0842163212', 50, 'nao', '54', 'Simples', 3, NULL, 1, 0, NULL, '2025-06-28 01:50:06'),
(21, 'fdgfdgd', 'teste@gmail.com', '$2y$10$/odL1gqBy3YFBlvgikyRFuoKwpbGUBL8hnR2RF9BzF6frfWLpsD8a', '+258 0842163212', 45, 'sim', '54', 'Simples', 3, NULL, 1, 0, NULL, '2025-06-28 01:50:37'),
(22, 'Maik', 'Maik@gmail.com', '$2y$10$3J93dy4NMExOO5pliMZAt.T3Da8mAVo.bPzsiv6w0epp/36rMuhB.', '879299196', 23, 'nao', 'Chingodzi', 'Simples', 3, NULL, 1, 0, NULL, '2025-06-28 02:33:37'),
(23, 'chimoio', 'chimoio@gmail.com', '$2y$10$UYrxjTmHikKe37gKwEsBS.1VKeTuOBsrAQjzYa2pamBQ2xVbAydhK', '870000000', NULL, 'nao', 'Chimoio', 'lider', 4, NULL, 1, 0, NULL, '2025-06-30 06:52:56'),
(24, 'levi thaunde', 'levithaunde@gmail.com', '$2y$10$oyAbwtqWERpZDu3uYY4zNOmgpOfO5X2hCVLneILa5jBOxJNg2R8wi', '879299196', NULL, 'nao', NULL, 'membro', 3, NULL, 1, 0, NULL, '2025-06-30 06:57:18'),
(25, 'lider2', 'lider2@gmail.com', '$2y$10$NeacMiccVk7CjtFe77l7zupJNeJpGe5AESYPaBCWMnL1gNTRNtTpa', '842163212', NULL, 'nao', 'Cidade de Tete', 'lider', 3, NULL, 1, 0, NULL, '2025-07-01 03:21:10'),
(26, 'Eduardo', 'edu@gmail.com', '$2y$10$dQl7TNvBaHBfZ5bGcw95C.Hh1IgGJyu7uRUK0G6dQLYPySgN9E2OC', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'lider', 3, NULL, 0, 0, NULL, '2025-07-01 04:31:42'),
(29, 'Ananias Manuel Joaquim Thaunde', 'hananiasthaunde1221@gmail.com', '$2y$10$a2f4tg0/HngCC.Jzpvft9ejHtLSbr/xqkNwVHJK5JpBm0VjGBu0bm', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-07-01 21:35:16'),
(30, 'Ananias Manuel Joaquim Thaunde', 'testeteste@gmail.com', '$2y$10$nn4p.6i93VuIkCm1COj7jOoA56WTFhsOaAXNeoNMGKnZupC3Q01g.', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-07-01 19:53:51'),
(31, 'Ananias Manuel Joaquim Thaunde', 'tete11@gmail.com', '$2y$10$etQheqISzF9ekDta/gLXwu3p6KWI3d02uJ5UbZF20JM7mqqsJSyM2', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'membro', 3, NULL, 0, 0, NULL, '2025-07-01 19:57:39'),
(33, 'Neyma Veloso', 'pastora@gmail.com', '$2y$10$NfYVm3jJ5ywo37tqls448uwixhf270uv9mOz8ualOENsod8dVZE6a', '847702390', NULL, 'nao', 'Cidade de Tete', 'pastor', 3, NULL, 1, 0, NULL, '2025-07-10 17:22:24'),
(35, 'Muler Abel António', 'pastor@gmail.com', '$2y$10$gGYfqYFIK5cLZQNmhyX1oenLVTXh30MXNJaN8apbgm3tbsiaH4oXq', '864949462', NULL, 'nao', 'Cidade de Tete', 'pastor', 3, NULL, 1, 0, NULL, '2025-07-10 17:49:16'),
(36, 'Isaias', 'pastorisaias@gmail.com', '$2y$10$RKguqifzTcoAZBeUbwX29OSuIFL5BV2vsDVseJQ/jSHvY1oqzN1mW', '+258 84 6540932', NULL, 'nao', 'Cidade de Nicoadala', 'Master_admin', 6, NULL, 1, 0, NULL, '2025-07-21 08:15:33'),
(42, 'Carlitos Rafael', 'carlitosrafael5@gmail.com', '$2y$10$Wv3sghilirXhWFVt/pQbEuLIaKHcgocHUbmAElvl4U6J8eM4wtXa6', '', NULL, 'nao', 'Chimoio', 'Master_admin', 4, NULL, 1, 0, NULL, '2025-08-02 11:32:29'),
(43, 'Ananias Manuel Joaquim Thaunde', 'hananiasthaundeteste@gmail.com', '$2y$10$mbYZeKumU1WIB1ledBzbKuK3BsKKSbueV7u6MKZqeP8P17Os/hesS', '+258 0842163212', NULL, 'nao', 'Cidade de Tete', 'pastor', 3, NULL, 0, 0, NULL, '2025-10-16 07:48:43'),
(44, 'Maik', 'maikerculano@gmail.com', '$2y$10$AT77/MnTJccGcMZWls9hBeAmpXtJYAveMPrKd2/gcYpRu25hb0fWO', '856997119', NULL, 'nao', 'Cidade de Tete', 'pastor', 7, 5, 1, 0, NULL, '2026-01-26 17:23:59'),
(45, 'Elísio Francisco', 'elisiofranciscojeronimo@gmail.com', '$2y$10$Lua2EvkulihJwm9PW.nxq.eUVL1kwAN6V3EvAlJLHwD3lB95T6Inu', '845673544', NULL, 'nao', 'Tete', 'lider', 7, 6, 1, 0, NULL, '2026-01-31 11:54:02'),
(46, 'Ananias Thaunde', 'athaundeoficial@gmail.com', '$2y$10$lTyZsd/fY2J5WmFLU5x3De3ZNOS0Rdbs3ccVR4t7sW5vW/TkZSpI6', '842163212', NULL, 'nao', 'Cidade de Tete', 'lider', 7, 5, 1, 0, NULL, '2026-01-31 11:55:49'),
(47, 'Edmilson', 'Edmilson@gmail.com', '$2y$10$G3AykoODKuxgdTZTXBqbnOjM0EmzTDylzrm.SCPWn7cCbmHamWZcK', '', NULL, 'nao', '', 'membro', 7, 6, 1, 0, NULL, '2026-01-31 12:10:18'),
(48, 'Turiano Jeronimo', 'Turiano@gmail.com', '$2y$10$znJQ4qnI7hJ7uKF.Xdm62.8s0pEH1quftAWXLYJtRx7S.Z6rFoDyy', '', NULL, 'nao', '', 'membro', 7, 5, 1, 0, NULL, '2026-01-31 12:10:36'),
(49, 'Jorge', 'Jorge@gmail.com', '$2y$10$aouV0.QcM81o9mo4v0QdL.FY2O8tjlY.vluNIY/rb./908IEizJEa', '', NULL, 'nao', '', 'membro', 7, 5, 1, 0, NULL, '2026-01-31 12:10:57'),
(50, 'Vania', 'Vania@gmail.com', '$2y$10$ilBlmX4fmTVV56vndknf3ufq1zrkUjLtECGW4b9AwAuWBdBWwo0Ey', '', NULL, 'nao', '', 'membro', 7, 5, 1, 0, NULL, '2026-01-31 12:11:25');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `celulas`
--
ALTER TABLE `celulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lider_id` (`lider_id`),
  ADD KEY `church_id` (`church_id`);

--
-- Índices de tabela `celula_relatorios`
--
ALTER TABLE `celula_relatorios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `celula_mes_unico` (`celula_id`,`mes_referencia`),
  ADD KEY `lider_id` (`lider_id`);

--
-- Índices de tabela `churches`
--
ALTER TABLE `churches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `church_id` (`church_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Índices de tabela `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`church_id`),
  ADD KEY `church_id` (`church_id`);

--
-- Índices de tabela `registo_atividades`
--
ALTER TABLE `registo_atividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `celula_id` (`celula_id`),
  ADD KEY `lider_id` (`lider_id`);

--
-- Índices de tabela `service_reports`
--
ALTER TABLE `service_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `church_id` (`church_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `tithes`
--
ALTER TABLE `tithes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `church_id` (`church_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `church_id` (`church_id`),
  ADD KEY `celula_id` (`celula_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT de tabela `celulas`
--
ALTER TABLE `celulas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `celula_relatorios`
--
ALTER TABLE `celula_relatorios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `churches`
--
ALTER TABLE `churches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `members`
--
ALTER TABLE `members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `registo_atividades`
--
ALTER TABLE `registo_atividades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `service_reports`
--
ALTER TABLE `service_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT de tabela `tithes`
--
ALTER TABLE `tithes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=228;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `attendances`
--
ALTER TABLE `attendances`
  ADD CONSTRAINT `attendances_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendances_ibfk_2` FOREIGN KEY (`report_id`) REFERENCES `service_reports` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `celulas`
--
ALTER TABLE `celulas`
  ADD CONSTRAINT `celulas_ibfk_1` FOREIGN KEY (`lider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `celulas_ibfk_2` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `celula_relatorios`
--
ALTER TABLE `celula_relatorios`
  ADD CONSTRAINT `celula_relatorios_ibfk_1` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `celula_relatorios_ibfk_2` FOREIGN KEY (`lider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Restrições para tabelas `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `registo_atividades`
--
ALTER TABLE `registo_atividades`
  ADD CONSTRAINT `registo_atividades_ibfk_1` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registo_atividades_ibfk_2` FOREIGN KEY (`lider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `service_reports`
--
ALTER TABLE `service_reports`
  ADD CONSTRAINT `service_reports_ibfk_1` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_reports_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tithes`
--
ALTER TABLE `tithes`
  ADD CONSTRAINT `tithes_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `service_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tithes_ibfk_2` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
