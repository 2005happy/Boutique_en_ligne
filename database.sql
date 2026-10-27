-- Création de la base de données
CREATE DATABASE IF NOT EXISTS gestion_boutique CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_boutique;

-- I. GESTION DES UTILISATEURS (Critères 1 à 10)
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vendeur', 'client') DEFAULT 'client',
    theme ENUM('clair', 'sombre') NULL DEFAULT NULL,
    tentatives_connexion INT DEFAULT 0,
    verrouille_jusqua DATETIME NULL,
    double_auth_secret VARCHAR(255) NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE historique_connexions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    ip_adresse VARCHAR(45),
    date_connexion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

CREATE TABLE journal_activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(255),
    details TEXT,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- II. GESTION DES DONNEES (Critères 11 à 19)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    archive TINYINT(1) DEFAULT 0
);

CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    description TEXT,
    prix DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    categorie_id INT,
    archive TINYINT(1) DEFAULT 0,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id)
);

-- III. GESTION DOCUMENTAIRE (Critères 20 à 25)
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(255) NOT NULL,
    type_mime VARCHAR(100),
    taille INT,
    version INT DEFAULT 1,
    id_parent INT NULL, -- Pour le versioning
    televerse_par INT,
    date_televersement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (televerse_par) REFERENCES utilisateurs(id)
);

-- V. SÉCURITÉ (Critères 39 à 43)
CREATE TABLE sauvegardes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_fichier VARCHAR(255),
    date_sauvegarde TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut VARCHAR(50)
);

USE gestion_boutique;

INSERT INTO categories (nom) VALUES ('Électronique'), ('Vêtements'), ('Maison');

INSERT INTO produits (nom, description, prix, stock, categorie_id) VALUES
('Smartphone X', 'Un smartphone performant avec 128Go de stockage.', 299.99, 15, 1),
('Casque Bluetooth', 'Casque sans fil avec réduction de bruit.', 89.90, 30, 1),
('T-shirt Coton', 'T-shirt 100% coton, disponible en plusieurs tailles.', 19.99, 50, 2),
('Lampe LED', 'Lampe de bureau LED réglable.', 24.50, 0, 3);
