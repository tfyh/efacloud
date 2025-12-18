-- efaCloud Test Database Schema
-- This file initializes the test database with minimal schema for integration tests

-- Create efaCloudUsers table (simplified for testing)
CREATE TABLE IF NOT EXISTS `efaCloudUsers` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `efaCloudUserID` INT NOT NULL,
    `Vorname` VARCHAR(255) DEFAULT NULL,
    `Nachname` VARCHAR(255) DEFAULT NULL,
    `efaAdminName` VARCHAR(255) DEFAULT NULL,
    `Passwort_Hash` VARCHAR(255) DEFAULT NULL,
    `Rolle` VARCHAR(50) DEFAULT 'member',
    `EMail` VARCHAR(255) DEFAULT NULL,
    `Workflows` INT DEFAULT 0,
    `Concessions` INT DEFAULT 0,
    `Subskriptionen` INT DEFAULT 0,
    `LastModified` BIGINT DEFAULT 0,
    PRIMARY KEY (`ID`),
    KEY `idx_efaCloudUserID` (`efaCloudUserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create efa2boats table (simplified for testing)
CREATE TABLE IF NOT EXISTS `efa2boats` (
    `Id` VARCHAR(36) NOT NULL,
    `ValidFrom` BIGINT NOT NULL DEFAULT 0,
    `InvalidFrom` BIGINT DEFAULT NULL,
    `Name` VARCHAR(255) NOT NULL,
    `TypeSeats` VARCHAR(50) DEFAULT NULL,
    `TypeRigging` VARCHAR(50) DEFAULT NULL,
    `TypeType` VARCHAR(50) DEFAULT NULL,
    `ChangeCount` INT DEFAULT 0,
    `LastModified` BIGINT DEFAULT 0,
    `LastModification` VARCHAR(50) DEFAULT NULL,
    `ecrid` VARCHAR(12) DEFAULT NULL,
    `ecrown` INT DEFAULT NULL,
    PRIMARY KEY (`Id`, `ValidFrom`),
    KEY `idx_name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create efa2persons table (simplified for testing)
CREATE TABLE IF NOT EXISTS `efa2persons` (
    `Id` VARCHAR(36) NOT NULL,
    `ValidFrom` BIGINT NOT NULL DEFAULT 0,
    `InvalidFrom` BIGINT DEFAULT NULL,
    `FirstName` VARCHAR(255) DEFAULT NULL,
    `LastName` VARCHAR(255) DEFAULT NULL,
    `FirstLastName` VARCHAR(512) DEFAULT NULL,
    `MembershipNo` VARCHAR(50) DEFAULT NULL,
    `ChangeCount` INT DEFAULT 0,
    `LastModified` BIGINT DEFAULT 0,
    `LastModification` VARCHAR(50) DEFAULT NULL,
    `ecrid` VARCHAR(12) DEFAULT NULL,
    `ecrown` INT DEFAULT NULL,
    PRIMARY KEY (`Id`, `ValidFrom`),
    KEY `idx_name` (`FirstLastName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create efa2boatstatus table (simplified for testing)
CREATE TABLE IF NOT EXISTS `efa2boatstatus` (
    `BoatId` VARCHAR(36) NOT NULL,
    `BoatText` VARCHAR(255) DEFAULT NULL,
    `Comment` TEXT DEFAULT NULL,
    `UnknownBoat` VARCHAR(5) DEFAULT NULL,
    `ChangeCount` INT DEFAULT 0,
    `LastModified` BIGINT DEFAULT 0,
    `ecrid` VARCHAR(12) DEFAULT NULL,
    `ecrown` INT DEFAULT NULL,
    PRIMARY KEY (`BoatId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create efa2logbook table (simplified for testing)
CREATE TABLE IF NOT EXISTS `efa2logbook` (
    `EntryId` INT NOT NULL,
    `Logbookname` VARCHAR(50) NOT NULL,
    `Date` DATE DEFAULT NULL,
    `BoatId` VARCHAR(36) DEFAULT NULL,
    `BoatName` VARCHAR(255) DEFAULT NULL,
    `CoxId` VARCHAR(36) DEFAULT NULL,
    `AllCrewIds` TEXT DEFAULT NULL,
    `AllCrewNames` TEXT DEFAULT NULL,
    `DestinationId` VARCHAR(36) DEFAULT NULL,
    `DestinationName` VARCHAR(255) DEFAULT NULL,
    `Distance` DECIMAL(10,2) DEFAULT NULL,
    `ChangeCount` INT DEFAULT 0,
    `LastModified` BIGINT DEFAULT 0,
    `LastModification` VARCHAR(50) DEFAULT NULL,
    `ecrid` VARCHAR(12) DEFAULT NULL,
    `ecrown` INT DEFAULT NULL,
    PRIMARY KEY (`EntryId`, `Logbookname`),
    KEY `idx_date` (`Date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert test admin user
INSERT INTO `efaCloudUsers` (`ID`, `efaCloudUserID`, `Vorname`, `Nachname`, `efaAdminName`, `Rolle`, `EMail`)
VALUES (1, 1, 'Test', 'Admin', 'testadmin', 'admin', 'test@example.com');

-- Insert test boat
INSERT INTO `efa2boats` (`Id`, `ValidFrom`, `Name`, `TypeSeats`, `TypeType`, `ecrid`)
VALUES ('test-boat-uuid-001', 0, 'Test Boat 1', '2x', 'RACING', 'AAAAAAAAAAAA');

-- Insert test person
INSERT INTO `efa2persons` (`Id`, `ValidFrom`, `FirstName`, `LastName`, `FirstLastName`, `ecrid`)
VALUES ('test-person-uuid-001', 0, 'Max', 'Mustermann', 'Max Mustermann', 'BBBBBBBBBBBB');
