/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.20-11.8.9-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: gestion_boutique
-- ------------------------------------------------------
-- Server version	11.8.9-MariaDB-2 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `archive` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES
(1,'Électronique',0),
(2,'Vêtements',0),
(3,'Maison',0);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `commande_lignes`
--

DROP TABLE IF EXISTS `commande_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commande_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commande_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `nom_produit` varchar(150) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `quantite` int(11) NOT NULL,
  `sous_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `commande_lignes_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commande_lignes_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_lignes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `commande_lignes` WRITE;
/*!40000 ALTER TABLE `commande_lignes` DISABLE KEYS */;
INSERT INTO `commande_lignes` VALUES
(2,2,3,'T-shirt Coton',19.99,15,299.85);
/*!40000 ALTER TABLE `commande_lignes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `client_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `statut` enum('en_attente','confirmee','expediee','livree','annulee') DEFAULT 'en_attente',
  `adresse_livraison` text NOT NULL,
  `ville` varchar(100) NOT NULL,
  `code_postal` varchar(20) NOT NULL,
  `telephone` varchar(30) NOT NULL,
  `notes` text DEFAULT NULL,
  `date_commande` timestamp NULL DEFAULT current_timestamp(),
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commandes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `commandes` WRITE;
/*!40000 ALTER TABLE `commandes` DISABLE KEYS */;
INSERT INTO `commandes` VALUES
(2,'CMD-20261022-F3ADC3',2,299.85,'confirmee','rubirizi,karombo','bujumbura','0001','61999652','20h','2026-10-22 23:18:58','2026-10-22 23:20:16');
/*!40000 ALTER TABLE `commandes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`),
  KEY `user2_id` (`user2_id`),
  CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_fichier` varchar(255) NOT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `type_mime` varchar(100) DEFAULT NULL,
  `taille` int(11) DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `id_parent` int(11) DEFAULT NULL,
  `televerse_par` int(11) DEFAULT NULL,
  `date_televersement` timestamp NULL DEFAULT current_timestamp(),
  `archive` tinyint(1) DEFAULT 0,
  `partage_actif` tinyint(1) DEFAULT 0,
  `token_partage` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `televerse_par` (`televerse_par`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`televerse_par`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES
(1,'Service web.xlsx','../uploads/doc_6ad93e1eb9a221.72761132.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',5249,1,NULL,1,'2026-10-21 20:35:10',0,0,NULL);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `historique_connexions`
--

DROP TABLE IF EXISTS `historique_connexions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `historique_connexions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) DEFAULT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `date_connexion` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `historique_connexions_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historique_connexions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `historique_connexions` WRITE;
/*!40000 ALTER TABLE `historique_connexions` DISABLE KEYS */;
INSERT INTO `historique_connexions` VALUES
(1,1,'127.0.0.1','2026-10-21 09:23:09'),
(2,1,'127.0.0.1','2026-10-21 09:23:41'),
(3,1,'127.0.0.1','2026-10-21 09:28:39'),
(4,1,'127.0.0.1','2026-10-21 09:40:42'),
(5,1,'127.0.0.1','2026-10-21 09:55:46'),
(6,1,'127.0.0.1','2026-10-21 09:56:05'),
(7,1,'127.0.0.1','2026-10-21 10:00:06'),
(8,1,'127.0.0.1','2026-10-21 10:02:54'),
(9,1,'127.0.0.1','2026-10-21 10:06:49'),
(10,2,'127.0.0.1','2026-10-21 10:20:19'),
(11,2,'127.0.0.1','2026-10-21 19:55:51'),
(12,1,'127.0.0.1','2026-10-21 19:59:00'),
(13,1,'127.0.0.1','2026-10-21 20:31:14'),
(14,1,'127.0.0.1','2026-10-21 21:02:02'),
(15,1,'127.0.0.1','2026-10-21 21:18:09'),
(16,2,'::1','2026-10-21 21:24:01'),
(17,1,'127.0.0.1','2026-10-21 22:07:29'),
(18,3,'127.0.0.1','2026-10-21 22:08:03'),
(19,1,'127.0.0.1','2026-10-21 22:08:29'),
(20,1,'127.0.0.1','2026-10-21 22:09:39'),
(21,3,'127.0.0.1','2026-10-21 22:10:05'),
(22,1,'127.0.0.1','2026-10-22 14:00:01'),
(23,1,'127.0.0.1','2026-10-22 19:02:40'),
(24,2,'127.0.0.1','2026-10-22 22:11:09'),
(25,1,'127.0.0.1','2026-10-22 22:31:36'),
(26,2,'::1','2026-10-22 23:02:51'),
(27,2,'127.0.0.1','2026-10-27 07:36:41'),
(28,1,'127.0.0.1','2026-10-27 08:53:07');
/*!40000 ALTER TABLE `historique_connexions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `journal_activites`
--

DROP TABLE IF EXISTS `journal_activites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_activites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `date_action` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `journal_activites_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_activites`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `journal_activites` WRITE;
/*!40000 ALTER TABLE `journal_activites` DISABLE KEYS */;
INSERT INTO `journal_activites` VALUES
(1,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 09:23:09'),
(2,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 09:23:41'),
(3,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 09:28:39'),
(4,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 09:40:42'),
(5,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 09:55:46'),
(6,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 09:56:05'),
(7,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 10:00:06'),
(8,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 10:02:54'),
(9,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 10:06:49'),
(10,2,'INSCRIPTION','Nouveau compte créé avec le rôle client','2026-10-21 10:20:03'),
(11,2,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 10:20:19'),
(12,2,'DEMANDE_RESET','Demande de réinitialisation pour jean@boutique.com','2026-10-21 19:55:00'),
(13,2,'RESET_MDP_OK','Mot de passe réinitialisé via token','2026-10-21 19:55:41'),
(14,2,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 19:55:51'),
(15,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 19:59:00'),
(16,1,'EXPORT_CONNEXIONS','Export CSV des connexions (12 lignes)','2026-10-21 20:29:00'),
(17,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 20:31:14'),
(18,1,'TELEVERSEMENT','Fichier : Service web.xlsx (v1)','2026-10-21 20:35:10'),
(19,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 21:02:02'),
(20,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 21:18:09'),
(21,2,'CONNEXION','Connexion réussie depuis ::1','2026-10-21 21:24:01'),
(22,1,'MODIF_THEME','Thème changé pour : sombre','2026-10-21 21:31:06'),
(23,1,'MODIF_PARAMETRES','Mise à jour des paramètres généraux','2026-10-21 21:31:33'),
(24,1,'MODIF_PARAMETRES','Mise à jour des paramètres généraux','2026-10-21 21:31:50'),
(25,1,'MODIF_PARAMETRES','Mise à jour des paramètres généraux','2026-10-21 21:32:03'),
(26,1,'MODIF_THEME','Thème changé pour : clair','2026-10-21 21:51:29'),
(27,1,'MODIF_THEME','Thème changé pour : sombre','2026-10-21 21:51:37'),
(28,1,'MODIF_THEME','Thème changé pour : clair','2026-10-21 22:05:56'),
(29,3,'INSCRIPTION','Nouveau compte : vendeur (statut: en_attente)','2026-10-21 22:06:59'),
(30,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 22:07:29'),
(31,1,'VALIDATION_USER','Approbation du compte #3','2026-10-21 22:07:42'),
(32,3,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 22:08:03'),
(33,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 22:08:29'),
(34,1,'MODIF_PARAMETRES','Mise à jour des paramètres généraux','2026-10-21 22:08:38'),
(35,1,'VERROU_USER','Verrouillage du compte #3','2026-10-21 22:09:11'),
(36,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 22:09:39'),
(37,1,'DEVERROU_USER','Déverrouillage du compte #3','2026-10-21 22:09:46'),
(38,3,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-21 22:10:05'),
(39,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-22 14:00:01'),
(40,1,'MODIF_USER','Modification de l\'utilisateur #3','2026-10-22 14:04:10'),
(41,1,'MODIF_USER','Modification de l\'utilisateur #3','2026-10-22 14:04:19'),
(42,1,'AJOUT_USER','Création de peniel@boutique.com (vendeur)','2026-10-22 14:05:12'),
(43,1,'SUPPR_USER','Suppression de l\'utilisateur #4','2026-10-22 14:05:31'),
(44,1,'EXPORT_CSV','Export CSV du journal (43 lignes)','2026-10-22 14:06:56'),
(45,1,'EXPORT_PDF','Rapport PDF : produits','2026-10-22 14:10:53'),
(46,1,'EXPORT_PDF','Rapport PDF : utilisateurs','2026-10-22 14:11:46'),
(47,1,'EXPORT_PDF','Rapport PDF : activites','2026-10-22 14:11:59'),
(48,1,'EXPORT_PDF','Rapport PDF : statistiques','2026-10-22 14:12:20'),
(49,1,'MODIF_THEME','Thème changé pour : sombre','2026-10-22 14:15:51'),
(50,1,'RESET_PARAMETRES','Réinitialisation des paramètres','2026-10-22 14:17:35'),
(51,1,'MODIF_THEME','Thème changé pour : sombre','2026-10-22 14:17:41'),
(52,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-22 19:02:40'),
(53,1,'MODIF_PRODUIT','Modification du produit #4','2026-10-22 19:04:05'),
(54,1,'MODIF_PRODUIT','Modification du produit #4','2026-10-22 19:04:11'),
(55,1,'MODIF_PRODUIT','Modification du produit #4','2026-10-22 19:04:36'),
(56,1,'SAUVEGARDE','Création de backup_2026-10-22_21-50-10.sql (mysqldump)','2026-10-22 19:50:10'),
(57,1,'RESTAURATION','Restauration depuis backup_2026-10-22_21-50-59.sql (71 requêtes, 0 erreurs)','2026-10-22 22:10:11'),
(58,1,'SUPPR_BACKUP','Suppression de secours_avant_restauration_2026-10-22_21-59-29.sql','2026-10-22 22:10:20'),
(59,1,'SUPPR_BACKUP','Suppression de secours_avant_restauration_2026-10-22_22-10-08.sql','2026-10-22 22:10:22'),
(60,1,'SUPPR_BACKUP','Suppression de secours_avant_restauration_2026-10-22_22-10-04.sql','2026-10-22 22:10:23'),
(61,1,'SUPPR_BACKUP','Suppression de secours_avant_restauration_2026-10-22_22-10-01.sql','2026-10-22 22:10:24'),
(62,2,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-22 22:11:09'),
(63,2,'CONSULTATION_PRODUIT','Produit #3 : T-shirt Coton','2026-10-22 22:11:14'),
(64,2,'CONSULTATION_PRODUIT','Produit #3 : T-shirt Coton','2026-10-22 22:12:04'),
(65,2,'CONSULTATION_PRODUIT','Produit #3 : T-shirt Coton','2026-10-22 22:14:19'),
(66,2,'CONSULTATION_PRODUIT','Produit #3 : T-shirt Coton','2026-10-22 22:14:24'),
(67,2,'CONSULTATION_PRODUIT','Produit #2 : Casque Bluetooth','2026-10-22 22:15:34'),
(68,2,'CONSULTATION_PRODUIT','Produit #1 : Smartphone X','2026-10-22 22:15:43'),
(69,2,'COMMANDE','Commande CMD-20261022-2C3580 — Total : 299.85 €','2026-10-22 22:29:17'),
(70,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-22 22:31:36'),
(71,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : confirmee → en_attente','2026-10-22 22:51:53'),
(72,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : en_attente → confirmee','2026-10-22 22:51:58'),
(73,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : confirmee → expediee','2026-10-22 22:52:03'),
(74,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : expediee → livree','2026-10-22 22:52:09'),
(75,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : livree → annulee','2026-10-22 22:52:13'),
(76,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : annulee → confirmee','2026-10-22 22:52:17'),
(77,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : confirmee → confirmee','2026-10-22 22:52:21'),
(78,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : confirmee → en_attente','2026-10-22 22:56:44'),
(79,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : en_attente → en_attente','2026-10-22 22:58:46'),
(80,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : en_attente → en_attente','2026-10-22 22:58:46'),
(81,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : en_attente → confirmee','2026-10-22 22:58:51'),
(82,2,'CONNEXION','Connexion réussie depuis ::1','2026-10-22 23:02:51'),
(83,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : confirmee → livree','2026-10-22 23:04:06'),
(84,1,'MAJ_COMMANDE','CMD-20261022-2C3580 : livree → livree','2026-10-22 23:13:50'),
(85,1,'SUPPR_COMMANDE','Suppression de CMD-20261022-2C3580','2026-10-22 23:14:10'),
(86,2,'CONSULTATION_PRODUIT','Produit #3 : T-shirt Coton','2026-10-22 23:14:38'),
(87,2,'CONSULTATION_PRODUIT','Produit #3 : T-shirt Coton','2026-10-22 23:14:48'),
(88,2,'COMMANDE','Commande CMD-20261022-F3ADC3 — Total : 299.85 €','2026-10-22 23:18:58'),
(89,1,'MAJ_COMMANDE','CMD-20261022-F3ADC3 : confirmee → livree','2026-10-22 23:19:25'),
(90,1,'MAJ_COMMANDE','CMD-20261022-F3ADC3 : livree → livree','2026-10-22 23:19:28'),
(91,1,'MAJ_COMMANDE','CMD-20261022-F3ADC3 : livree → confirmee','2026-10-22 23:19:37'),
(92,1,'MAJ_COMMANDE','CMD-20261022-F3ADC3 : confirmee → livree','2026-10-22 23:19:43'),
(93,1,'MAJ_COMMANDE','CMD-20261022-F3ADC3 : livree → confirmee','2026-10-22 23:20:16'),
(94,1,'MAJ_COMMANDE','CMD-20261022-F3ADC3 : confirmee → confirmee','2026-10-22 23:40:15'),
(95,2,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-27 07:36:41'),
(96,1,'CONNEXION','Connexion réussie depuis 127.0.0.1','2026-10-27 08:53:07');
/*!40000 ALTER TABLE `journal_activites` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `expediteur_id` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `date_envoi` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `expediteur_id` (`expediteur_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `parametres`
--

DROP TABLE IF EXISTS `parametres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parametres` (
  `cle` varchar(64) NOT NULL,
  `valeur` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `modifie_le` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parametres`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `parametres` WRITE;
/*!40000 ALTER TABLE `parametres` DISABLE KEYS */;
INSERT INTO `parametres` VALUES
('devise','€','Symbole monétaire','2026-10-22 14:17:35'),
('elements_par_page','20','Éléments par page','2026-10-22 14:17:35'),
('email_contact','contact@maboutique.com','Email de contact public','2026-10-22 14:17:35'),
('inscription_ouverte','1','Autoriser les inscriptions','2026-10-22 14:17:35'),
('maintenance','0','Mode maintenance','2026-10-22 14:17:35'),
('nom_boutique','Ma Boutique','Nom affiché de la boutique','2026-10-22 14:17:35'),
('slogan','Qualité et confiance depuis 2024','Slogan de la boutique','2026-10-22 14:17:35'),
('telephone','+33 1 23 45 67 89','Téléphone de contact','2026-10-22 14:17:35'),
('theme','sombre','Thème par défaut','2026-10-22 14:17:41'),
('validation_vendeurs','1','Validation requise pour vendeurs','2026-10-22 14:17:35');
/*!40000 ALTER TABLE `parametres` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expire_le` datetime NOT NULL,
  `utilise` tinyint(1) DEFAULT 0,
  `cree_le` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES
(1,2,'f669912bb53102725ba0dd3527e015c273af296d0f60f1a4f759c4aee38465d2','2026-10-21 22:55:00',1,'2026-10-21 19:55:00');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `produits`
--

DROP TABLE IF EXISTS `produits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `prix` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `categorie_id` int(11) DEFAULT NULL,
  `vendeur_id` int(11) DEFAULT NULL,
  `archive` tinyint(1) DEFAULT 0,
  `cree_le` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `categorie_id` (`categorie_id`),
  KEY `fk_produits_vendeur` (`vendeur_id`),
  CONSTRAINT `fk_produits_vendeur` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produits`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `produits` WRITE;
/*!40000 ALTER TABLE `produits` DISABLE KEYS */;
INSERT INTO `produits` VALUES
(1,'Smartphone X','Un smartphone performant avec 128Go de stockage.',299.99,15,1,NULL,0,'2026-10-21 08:57:54'),
(2,'Casque Bluetooth','Casque sans fil avec réduction de bruit.',89.90,30,1,NULL,0,'2026-10-21 08:57:54'),
(3,'T-shirt Coton','T-shirt 100% coton, disponible en plusieurs tailles.',19.99,35,2,NULL,0,'2026-10-21 08:57:54'),
(4,'Lampe LED','Lampe de bureau LED réglable.',24.50,0,3,NULL,0,'2026-10-21 08:57:54');
/*!40000 ALTER TABLE `produits` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sauvegardes`
--

DROP TABLE IF EXISTS `sauvegardes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sauvegardes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_fichier` varchar(255) DEFAULT NULL,
  `date_sauvegarde` timestamp NULL DEFAULT current_timestamp(),
  `statut` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sauvegardes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sauvegardes` WRITE;
/*!40000 ALTER TABLE `sauvegardes` DISABLE KEYS */;
/*!40000 ALTER TABLE `sauvegardes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin','vendeur','client') DEFAULT 'client',
  `statut` enum('actif','en_attente','rejete') DEFAULT 'actif',
  `tentatives_connexion` int(11) DEFAULT 0,
  `verrouille_jusqua` datetime DEFAULT NULL,
  `double_auth_secret` varchar(255) DEFAULT NULL,
  `cree_le` timestamp NULL DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `valide_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateurs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `utilisateurs` WRITE;
/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES
(1,'Administrateur','admin@boutique.com','$2y$12$wU8B7EEInZlq/k/DTk08eunFuV.F3HjduE3691AaTpVQpp4KK8wsW','admin','actif',0,NULL,NULL,'2026-10-21 09:12:58',NULL,NULL),
(2,'jean','jean@boutique.com','$2y$12$af4Run99FjknIFzDz0l2KuAFAwI53QqMD0zROyvnhyDBa1Xp5ao0q','client','actif',0,NULL,NULL,'2026-10-21 10:20:03',NULL,NULL),
(3,'happy','happy@boutique.com','$2y$12$Yz4w3Y1vardkcz.S6UgiCuYsaUDY3OryX.IEewkXca4S/c9HUr40y','vendeur','actif',0,NULL,NULL,'2026-10-21 22:06:59','2026-10-22 02:07:42',1);
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-10-27 11:01:40
