-- Sauvegarde Ma Boutique
-- Généré le 22/10/2026 22:09:57

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `archive` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `nom`, `archive`) VALUES
(1, 'Électronique', 0),
(2, 'Vêtements', 0),
(3, 'Maison', 0);

DROP TABLE IF EXISTS `documents`;
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

INSERT INTO `documents` (`id`, `nom_fichier`, `chemin_fichier`, `type_mime`, `taille`, `version`, `id_parent`, `televerse_par`, `date_televersement`, `archive`, `partage_actif`, `token_partage`) VALUES
(1, 'Service web.xlsx', '../uploads/doc_6ad93e1eb9a221.72761132.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 5249, 1, NULL, 1, '2026-10-21 22:35:10', 0, 0, NULL);

DROP TABLE IF EXISTS `historique_connexions`;
CREATE TABLE `historique_connexions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) DEFAULT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `date_connexion` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `historique_connexions_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `historique_connexions` (`id`, `utilisateur_id`, `ip_adresse`, `date_connexion`) VALUES
(1, 1, '127.0.0.1', '2026-10-21 11:23:09'),
(2, 1, '127.0.0.1', '2026-10-21 11:23:41'),
(3, 1, '127.0.0.1', '2026-10-21 11:28:39'),
(4, 1, '127.0.0.1', '2026-10-21 11:40:42'),
(5, 1, '127.0.0.1', '2026-10-21 11:55:46'),
(6, 1, '127.0.0.1', '2026-10-21 11:56:05'),
(7, 1, '127.0.0.1', '2026-10-21 12:00:06'),
(8, 1, '127.0.0.1', '2026-10-21 12:02:54'),
(9, 1, '127.0.0.1', '2026-10-21 12:06:49'),
(10, 2, '127.0.0.1', '2026-10-21 12:20:19'),
(11, 2, '127.0.0.1', '2026-10-21 21:55:51'),
(12, 1, '127.0.0.1', '2026-10-21 21:59:00'),
(13, 1, '127.0.0.1', '2026-10-21 22:31:14'),
(14, 1, '127.0.0.1', '2026-10-21 23:02:02'),
(15, 1, '127.0.0.1', '2026-10-21 23:18:09'),
(16, 2, '::1', '2026-10-21 23:24:01'),
(17, 1, '127.0.0.1', '2026-10-22 00:07:29'),
(18, 3, '127.0.0.1', '2026-10-22 00:08:03'),
(19, 1, '127.0.0.1', '2026-10-22 00:08:29'),
(20, 1, '127.0.0.1', '2026-10-22 00:09:39'),
(21, 3, '127.0.0.1', '2026-10-22 00:10:05'),
(22, 1, '127.0.0.1', '2026-10-22 16:00:01'),
(23, 1, '127.0.0.1', '2026-10-22 21:02:40');

DROP TABLE IF EXISTS `journal_activites`;
CREATE TABLE `journal_activites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `date_action` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `journal_activites_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `journal_activites` (`id`, `utilisateur_id`, `action`, `details`, `date_action`) VALUES
(1, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 11:23:09'),
(2, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 11:23:41'),
(3, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 11:28:39'),
(4, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 11:40:42'),
(5, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 11:55:46'),
(6, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 11:56:05'),
(7, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 12:00:06'),
(8, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 12:02:54'),
(9, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 12:06:49'),
(10, 2, 'INSCRIPTION', 'Nouveau compte créé avec le rôle client', '2026-10-21 12:20:03'),
(11, 2, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 12:20:19'),
(12, 2, 'DEMANDE_RESET', 'Demande de réinitialisation pour jean@boutique.com', '2026-10-21 21:55:00'),
(13, 2, 'RESET_MDP_OK', 'Mot de passe réinitialisé via token', '2026-10-21 21:55:41'),
(14, 2, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 21:55:51'),
(15, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 21:59:00'),
(16, 1, 'EXPORT_CONNEXIONS', 'Export CSV des connexions (12 lignes)', '2026-10-21 22:29:00'),
(17, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 22:31:14'),
(18, 1, 'TELEVERSEMENT', 'Fichier : Service web.xlsx (v1)', '2026-10-21 22:35:10'),
(19, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 23:02:02'),
(20, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-21 23:18:09'),
(21, 2, 'CONNEXION', 'Connexion réussie depuis ::1', '2026-10-21 23:24:01'),
(22, 1, 'MODIF_THEME', 'Thème changé pour : sombre', '2026-10-21 23:31:06'),
(23, 1, 'MODIF_PARAMETRES', 'Mise à jour des paramètres généraux', '2026-10-21 23:31:33'),
(24, 1, 'MODIF_PARAMETRES', 'Mise à jour des paramètres généraux', '2026-10-21 23:31:50'),
(25, 1, 'MODIF_PARAMETRES', 'Mise à jour des paramètres généraux', '2026-10-21 23:32:03'),
(26, 1, 'MODIF_THEME', 'Thème changé pour : clair', '2026-10-21 23:51:29'),
(27, 1, 'MODIF_THEME', 'Thème changé pour : sombre', '2026-10-21 23:51:37'),
(28, 1, 'MODIF_THEME', 'Thème changé pour : clair', '2026-10-22 00:05:56'),
(29, 3, 'INSCRIPTION', 'Nouveau compte : vendeur (statut: en_attente)', '2026-10-22 00:06:59'),
(30, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 00:07:29'),
(31, 1, 'VALIDATION_USER', 'Approbation du compte #3', '2026-10-22 00:07:42'),
(32, 3, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 00:08:03'),
(33, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 00:08:29'),
(34, 1, 'MODIF_PARAMETRES', 'Mise à jour des paramètres généraux', '2026-10-22 00:08:38'),
(35, 1, 'VERROU_USER', 'Verrouillage du compte #3', '2026-10-22 00:09:11'),
(36, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 00:09:39'),
(37, 1, 'DEVERROU_USER', 'Déverrouillage du compte #3', '2026-10-22 00:09:46'),
(38, 3, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 00:10:05'),
(39, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 16:00:01'),
(40, 1, 'MODIF_USER', 'Modification de l\'utilisateur #3', '2026-10-22 16:04:10'),
(41, 1, 'MODIF_USER', 'Modification de l\'utilisateur #3', '2026-10-22 16:04:19'),
(42, 1, 'AJOUT_USER', 'Création de peniel@boutique.com (vendeur)', '2026-10-22 16:05:12'),
(43, 1, 'SUPPR_USER', 'Suppression de l\'utilisateur #4', '2026-10-22 16:05:31'),
(44, 1, 'EXPORT_CSV', 'Export CSV du journal (43 lignes)', '2026-10-22 16:06:56'),
(45, 1, 'EXPORT_PDF', 'Rapport PDF : produits', '2026-10-22 16:10:53'),
(46, 1, 'EXPORT_PDF', 'Rapport PDF : utilisateurs', '2026-10-22 16:11:46'),
(47, 1, 'EXPORT_PDF', 'Rapport PDF : activites', '2026-10-22 16:11:59'),
(48, 1, 'EXPORT_PDF', 'Rapport PDF : statistiques', '2026-10-22 16:12:20'),
(49, 1, 'MODIF_THEME', 'Thème changé pour : sombre', '2026-10-22 16:15:51'),
(50, 1, 'RESET_PARAMETRES', 'Réinitialisation des paramètres', '2026-10-22 16:17:35'),
(51, 1, 'MODIF_THEME', 'Thème changé pour : sombre', '2026-10-22 16:17:41'),
(52, 1, 'CONNEXION', 'Connexion réussie depuis 127.0.0.1', '2026-10-22 21:02:40'),
(53, 1, 'MODIF_PRODUIT', 'Modification du produit #4', '2026-10-22 21:04:05'),
(54, 1, 'MODIF_PRODUIT', 'Modification du produit #4', '2026-10-22 21:04:11'),
(55, 1, 'MODIF_PRODUIT', 'Modification du produit #4', '2026-10-22 21:04:36'),
(56, 1, 'SAUVEGARDE', 'Création de backup_2026-10-22_21-50-10.sql (mysqldump)', '2026-10-22 21:50:10'),
(57, 1, 'RESTAURATION', 'Restauration depuis backup_2026-10-22_21-50-59.sql (71 requêtes, 0 erreurs)', '2026-10-22 23:59:32');

DROP TABLE IF EXISTS `parametres`;
CREATE TABLE `parametres` (
  `cle` varchar(64) NOT NULL,
  `valeur` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `modifie_le` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `parametres` (`cle`, `valeur`, `description`, `modifie_le`) VALUES
('devise', '€', 'Symbole monétaire', '2026-10-22 16:17:35'),
('elements_par_page', '20', 'Éléments par page', '2026-10-22 16:17:35'),
('email_contact', 'contact@maboutique.com', 'Email de contact public', '2026-10-22 16:17:35'),
('inscription_ouverte', '1', 'Autoriser les inscriptions', '2026-10-22 16:17:35'),
('maintenance', '0', 'Mode maintenance', '2026-10-22 16:17:35'),
('nom_boutique', 'Ma Boutique', 'Nom affiché de la boutique', '2026-10-22 16:17:35'),
('slogan', 'Qualité et confiance depuis 2024', 'Slogan de la boutique', '2026-10-22 16:17:35'),
('telephone', '+33 1 23 45 67 89', 'Téléphone de contact', '2026-10-22 16:17:35'),
('theme', 'sombre', 'Thème par défaut', '2026-10-22 16:17:41'),
('validation_vendeurs', '1', 'Validation requise pour vendeurs', '2026-10-22 16:17:35');

DROP TABLE IF EXISTS `password_resets`;
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

INSERT INTO `password_resets` (`id`, `utilisateur_id`, `token`, `expire_le`, `utilise`, `cree_le`) VALUES
(1, 2, 'f669912bb53102725ba0dd3527e015c273af296d0f60f1a4f759c4aee38465d2', '2026-10-21 22:55:00', 1, '2026-10-21 21:55:00');

DROP TABLE IF EXISTS `produits`;
CREATE TABLE `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `prix` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `categorie_id` int(11) DEFAULT NULL,
  `archive` tinyint(1) DEFAULT 0,
  `cree_le` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `categorie_id` (`categorie_id`),
  CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `produits` (`id`, `nom`, `description`, `prix`, `stock`, `categorie_id`, `archive`, `cree_le`) VALUES
(1, 'Smartphone X', 'Un smartphone performant avec 128Go de stockage.', '299.99', 15, 1, 0, '2026-10-21 10:57:54'),
(2, 'Casque Bluetooth', 'Casque sans fil avec réduction de bruit.', '89.90', 30, 1, 0, '2026-10-21 10:57:54'),
(3, 'T-shirt Coton', 'T-shirt 100% coton, disponible en plusieurs tailles.', '19.99', 50, 2, 0, '2026-10-21 10:57:54'),
(4, 'Lampe LED', 'Lampe de bureau LED réglable.', '24.50', 0, 3, 0, '2026-10-21 10:57:54');

DROP TABLE IF EXISTS `sauvegardes`;
CREATE TABLE `sauvegardes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_fichier` varchar(255) DEFAULT NULL,
  `date_sauvegarde` timestamp NULL DEFAULT current_timestamp(),
  `statut` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `utilisateurs`;
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

INSERT INTO `utilisateurs` (`id`, `nom`, `email`, `mot_de_passe`, `role`, `statut`, `tentatives_connexion`, `verrouille_jusqua`, `double_auth_secret`, `cree_le`, `date_validation`, `valide_par`) VALUES
(1, 'Administrateur', 'admin@boutique.com', '$2y$12$wU8B7EEInZlq/k/DTk08eunFuV.F3HjduE3691AaTpVQpp4KK8wsW', 'admin', 'actif', 0, NULL, NULL, '2026-10-21 11:12:58', NULL, NULL),
(2, 'jean', 'jean@boutique.com', '$2y$12$af4Run99FjknIFzDz0l2KuAFAwI53QqMD0zROyvnhyDBa1Xp5ao0q', 'client', 'actif', 0, NULL, NULL, '2026-10-21 12:20:03', NULL, NULL),
(3, 'happy', 'happy@boutique.com', '$2y$12$Yz4w3Y1vardkcz.S6UgiCuYsaUDY3OryX.IEewkXca4S/c9HUr40y', 'vendeur', 'actif', 0, NULL, NULL, '2026-10-22 00:06:59', '2026-10-22 02:07:42', 1);

SET FOREIGN_KEY_CHECKS = 1;
