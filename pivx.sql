-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 13. Jun 2019 um 12:44
-- Server-Version: 10.3.12-MariaDB
-- PHP-Version: 7.3.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `pivx`
-- Version 11
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `block`
--

CREATE TABLE `block` (
  `hash` varchar(64) CHARACTER SET ascii NOT NULL,
  `height` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `input`
--

CREATE TABLE `input` (
  `txid` varchar(64) CHARACTER SET ascii NOT NULL,
  `prevTxid` varchar(64) CHARACTER SET ascii NOT NULL,
  `prevN` int(11) NOT NULL,
  `address` varchar(34) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(17,8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `output`
--

CREATE TABLE `output` (
  `txid` varchar(64) CHARACTER SET ascii NOT NULL,
  `n` int(11) NOT NULL,
  `address` varchar(34) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value` decimal(17,8) NOT NULL,
  `created` decimal(17,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `transaction`
--

CREATE TABLE `transaction` (
  `txid` varchar(64) CHARACTER SET ascii NOT NULL,
  `blockhash` varchar(64) CHARACTER SET ascii NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `block`
--
ALTER TABLE `block`
  ADD PRIMARY KEY (`hash`),
  ADD KEY `hash` (`hash`);

--
-- Indizes für die Tabelle `input`
--
ALTER TABLE `input`
  ADD PRIMARY KEY (`prevTxid`,`prevN`),
  ADD KEY `txid` (`txid`),
  ADD KEY `prevTxid-input` (`prevTxid`),
  ADD KEY `prevN-input` (`prevN`);

--
-- Indizes für die Tabelle `output`
--
ALTER TABLE `output`
  ADD PRIMARY KEY (`txid`,`n`),
  ADD KEY `txid` (`txid`),
  ADD KEY `n` (`n`);

--
-- Indizes für die Tabelle `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`txid`),
  ADD KEY `txid` (`txid`),
  ADD KEY `blockhash` (`blockhash`);

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `input`
--
ALTER TABLE `input`
  ADD CONSTRAINT `prevN-input` FOREIGN KEY (`prevN`) REFERENCES `output` (`n`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `prevTxid-input` FOREIGN KEY (`prevTxid`) REFERENCES `output` (`txid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `txid-input` FOREIGN KEY (`txid`) REFERENCES `transaction` (`txid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `output`
--
ALTER TABLE `output`
  ADD CONSTRAINT `txid-output` FOREIGN KEY (`txid`) REFERENCES `transaction` (`txid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `transaction`
--
ALTER TABLE `transaction`
  ADD CONSTRAINT `blockhash` FOREIGN KEY (`blockhash`) REFERENCES `block` (`hash`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
