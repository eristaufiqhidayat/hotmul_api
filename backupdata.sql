-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Sep 09, 2025 at 09:32 PM
-- Server version: 8.0.42
-- PHP Version: 8.2.29 dari sftp

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hotmulquran`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`%` PROCEDURE `generate_ayah_harian` ()   BEGIN
    DECLARE j INT DEFAULT 1;
    DECLARE totalAyat INT;
    DECLARE ayatPerHari INT;
    DECLARE sisa INT;
    DECLARE hari INT;
    DECLARE offsetPos INT;
    DECLARE startSurah INT;
    DECLARE startAyah INT;
    DECLARE endSurah INT;
    DECLARE endAyah INT;
    DECLARE endOffset INT;

    -- kosongkan tabel target
    TRUNCATE TABLE ayahharian;

    WHILE j <= 30 DO
        -- hitung jumlah ayat dalam juz j
        SELECT COUNT(*) INTO totalAyat
        FROM quran_text
        WHERE juz = j;

        IF totalAyat > 0 THEN
            SET ayatPerHari = FLOOR(totalAyat / 30);
            SET sisa = totalAyat MOD 30;
            SET offsetPos = 0;
            SET hari = 1;

            WHILE hari <= 30 DO
                -- ambil ayat awal
                SELECT surah, ayah
                INTO startSurah, startAyah
                FROM quran_text
                WHERE juz = j
                ORDER BY surah, ayah
                LIMIT offsetPos, 1;

                -- tentukan jumlah ayat hari ini
                IF hari <= sisa THEN
                    SET offsetPos = offsetPos + ayatPerHari + 1;
                ELSE
                    SET offsetPos = offsetPos + ayatPerHari;
                END IF;

                SET endOffset = offsetPos - 1;

                -- ambil ayat akhir
                SELECT surah, ayah
                INTO endSurah, endAyah
                FROM quran_text
                WHERE juz = j
                ORDER BY surah, ayah
                LIMIT endOffset, 1;

                -- hanya insert kalau tidak null
                IF startSurah IS NOT NULL AND endSurah IS NOT NULL THEN
                    INSERT INTO ayahharian
                        (juz, hari_ke,
                         no_surahawal, nama_surahawal, no_ayahawal,
                         no_surahakhir, nama_surahakhir, no_ayahakhir)
                    VALUES
                        (j, hari,
                         startSurah, '', startAyah,
                         endSurah, '', endAyah);
                END IF;

                SET hari = hari + 1;
            END WHILE;
        END IF;

        SET j = j + 1;
    END WHILE;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `insert_dummy_juz` (IN `periode` INT(10), IN `group_id` INT(10), IN `tanggal` DATE)   BEGIN
  DECLARE i INT DEFAULT 1;

  WHILE i <= 30 DO
    INSERT INTO khotmul ( group_id,juz,periode,tanggal)
    VALUES (group_id,i,periode,'2025-09-01');
    SET i = i + 1;
  END WHILE;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `insert_khotmul_with_fill_legacy` (IN `p_group_id` INT, IN `p_periode` INT, IN `p_tanggal` DATE, IN `p_status` VARCHAR(50))   BEGIN
  DECLARE v_n INT DEFAULT 1;

  -- buat sequence 1..30 di temporary table
  CREATE TEMPORARY TABLE IF NOT EXISTS _seq (n INT PRIMARY KEY);
  TRUNCATE TABLE _seq;
  WHILE v_n <= 30 DO
    INSERT INTO _seq (n) VALUES (v_n);
    SET v_n = v_n + 1;
  END WHILE;

  -- nomor urut anggota dengan user-variable
  DROP TEMPORARY TABLE IF EXISTS _grp;
  CREATE TEMPORARY TABLE _grp (rn INT PRIMARY KEY, anggota_id INT);

  SET @rn := 0;
  INSERT INTO _grp (rn, anggota_id)
  SELECT (@rn := @rn + 1) AS rn, g.user_id
  FROM `daurah_anggota` g
  WHERE g.group_id = p_group_id
  ORDER BY g.user_id;

  -- insert join seq dengan daftar anggota (jika kurang dari 30 => anggota_id NULL)
  INSERT INTO khotmul (group_id, juz, periode, tanggal, anggota_id, status)
  SELECT 
    p_group_id,
    s.n AS juz,
    p_periode,
    p_tanggal,
    gr.anggota_id,
    p_status
  FROM _seq s
  LEFT JOIN _grp gr ON gr.rn = s.n;

  DROP TEMPORARY TABLE IF EXISTS _seq;
  DROP TEMPORARY TABLE IF EXISTS _grp;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `ayahharian`
--

CREATE TABLE `ayahharian` (
  `id` int NOT NULL,
  `juz` int NOT NULL,
  `hari_ke` int NOT NULL,
  `no_surahawal` int NOT NULL,
  `nama_surahawal` varchar(100) DEFAULT NULL,
  `no_ayahawal` int NOT NULL,
  `no_surahakhir` int NOT NULL,
  `nama_surahakhir` varchar(100) DEFAULT NULL,
  `no_ayahakhir` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ayahharian`
--

INSERT INTO `ayahharian` (`id`, `juz`, `hari_ke`, `no_surahawal`, `nama_surahawal`, `no_ayahawal`, `no_surahakhir`, `nama_surahakhir`, `no_ayahakhir`) VALUES
(1, 1, 1, 1, '', 1, 1, '', 1),
(2, 1, 2, 1, '', 2, 1, '', 2),
(3, 1, 3, 1, '', 3, 1, '', 3),
(4, 1, 4, 1, '', 4, 1, '', 4),
(5, 1, 5, 1, '', 5, 1, '', 5),
(6, 1, 6, 1, '', 6, 1, '', 6),
(7, 1, 7, 1, '', 7, 1, '', 7),
(8, 1, 8, 2, '', 1, 2, '', 1),
(9, 1, 9, 2, '', 2, 2, '', 2),
(10, 1, 10, 2, '', 2, 2, '', 2),
(11, 1, 11, 2, '', 2, 2, '', 2),
(12, 1, 12, 2, '', 2, 2, '', 2),
(13, 1, 13, 2, '', 2, 2, '', 2),
(14, 1, 14, 2, '', 2, 2, '', 2),
(15, 1, 15, 2, '', 2, 2, '', 2),
(16, 1, 16, 2, '', 2, 2, '', 2),
(17, 1, 17, 2, '', 2, 2, '', 2),
(18, 1, 18, 2, '', 2, 2, '', 2),
(19, 1, 19, 2, '', 2, 2, '', 2),
(20, 1, 20, 2, '', 2, 2, '', 2),
(21, 1, 21, 2, '', 2, 2, '', 2),
(22, 1, 22, 2, '', 2, 2, '', 2),
(23, 1, 23, 2, '', 2, 2, '', 2),
(24, 1, 24, 2, '', 2, 2, '', 2),
(25, 1, 25, 2, '', 2, 2, '', 2),
(26, 1, 26, 2, '', 2, 2, '', 2),
(27, 1, 27, 2, '', 2, 2, '', 2),
(28, 1, 28, 2, '', 2, 2, '', 2),
(29, 1, 29, 2, '', 2, 2, '', 2),
(30, 1, 30, 2, '', 2, 2, '', 2);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `id` int NOT NULL,
  `key` varchar(512) DEFAULT NULL,
  `value` mediumtext,
  `expiration` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`id`, `key`, `value`, `expiration`) VALUES
(19, 'laravel-cache-URHobwm4I3LpvtdO', 'a:1:{s:11:\"valid_until\";i:1756635824;}', 1757839184),
(20, 'laravel-cache-eXwRuTjQdYQyrSWt', 'a:1:{s:11:\"valid_until\";i:1756639572;}', 1757839152),
(21, 'laravel-cache-8em4Dr7HoNMHboNf', 'a:1:{s:11:\"valid_until\";i:1756646812;}', 1757839132),
(22, 'laravel-cache-3ELQPrJDB8ThFXjI', 'a:1:{s:11:\"valid_until\";i:1756683610;}', 1757856670),
(23, 'laravel-cache-ufxjlJOOi37tZZFX', 'a:1:{s:11:\"valid_until\";i:1756687979;}', 1757893499),
(24, 'laravel-cache-hkkOXrf0NhuqfHtf', 'a:1:{s:11:\"valid_until\";i:1756691753;}', 1757893493),
(25, 'laravel-cache-fSSPPAeG8SLvKVOY', 'a:1:{s:11:\"valid_until\";i:1756696302;}', 1757893482),
(26, 'laravel-cache-hUg93vdpVIct33xP', 'a:1:{s:11:\"valid_until\";i:1756701375;}', 1757893455),
(27, 'laravel-cache-cHG6aI2YHveHOSOK', 'a:1:{s:11:\"valid_until\";i:1756710193;}', 1757893453),
(28, 'laravel-cache-ThSY6YiNHy2ZtRiA', 'a:1:{s:11:\"valid_until\";i:1756729533;}', 1757893473),
(29, 'laravel-cache-BzOJD1hYaxzcobAx', 'a:1:{s:11:\"valid_until\";i:1756733368;}', 1757893468),
(30, 'laravel-cache-atD6wKATDxWDJeB4', 'a:1:{s:11:\"valid_until\";i:1756761647;}', 1757893487),
(31, 'laravel-cache-ZhDRhqaDUfglaLwR', 'a:1:{s:11:\"valid_until\";i:1756765251;}', 1757893491),
(32, 'laravel-cache-4pth2zDAlqtzvbNi', 'a:1:{s:11:\"valid_until\";i:1756771303;}', 1757893483),
(33, 'laravel-cache-4pth2zDAlqtzvbNi', 'a:1:{s:11:\"valid_until\";i:1756771305;}', 1757893485),
(34, 'laravel-cache-4pth2zDAlqtzvbNi', 'a:1:{s:11:\"valid_until\";i:1756771307;}', 1757893487),
(35, 'laravel-cache-itG3Repnb8znFIXD', 'a:1:{s:11:\"valid_until\";i:1756778013;}', 1757893473),
(36, 'laravel-cache-xXehVQzTVqBTziAZ', 'a:1:{s:11:\"valid_until\";i:1756788140;}', 1757893460),
(37, 'laravel-cache-DbHAz2xkw49yukSU', 'a:1:{s:11:\"valid_until\";i:1756802535;}', 1757893455),
(38, 'laravel-cache-pE5Iil81MsYmQJQ3', 'a:1:{s:11:\"valid_until\";i:1756806800;}', 1757893460),
(39, 'laravel-cache-TYMZW3mZSPuJg2ih', 'a:1:{s:11:\"valid_until\";i:1756816908;}', 1757893488),
(40, 'laravel-cache-PwWpUbTfQkOsxav3', 'a:1:{s:11:\"valid_until\";i:1756836155;}', 1757893475),
(41, 'laravel-cache-xp39I3q2wdH3NMo2', 'a:1:{s:11:\"valid_until\";i:1756856603;}', 1758052103),
(42, 'laravel-cache-hG7Kv8wrrduBQt5M', 'a:1:{s:11:\"valid_until\";i:1756860360;}', 1758052140),
(43, 'laravel-cache-jOGDfbZEQ0rWwTLf', 'a:1:{s:11:\"valid_until\";i:1756864297;}', 1758052117),
(44, 'laravel-cache-x27FWMGLCSg1Lmek', 'a:1:{s:11:\"valid_until\";i:1756867923;}', 1758052143),
(45, 'laravel-cache-VQv75mfLiUjx2C2h', 'a:1:{s:11:\"valid_until\";i:1756887478;}', 1758080218),
(46, 'laravel-cache-z66iw63C8GAtbeq0', 'a:1:{s:11:\"valid_until\";i:1756891264;}', 1758080224),
(47, 'laravel-cache-dGZ7IEgvzeCFGf6L', 'a:1:{s:11:\"valid_until\";i:1756895660;}', 1758080180),
(48, 'laravel-cache-1nzzFRfwGXXqS0aC', 'a:1:{s:11:\"valid_until\";i:1756902457;}', 1758080197),
(49, 'laravel-cache-r8CuZsGaGWAMw42D', 'a:1:{s:11:\"valid_until\";i:1756906229;}', 1758080189),
(50, 'laravel-cache-W8hO9zHIgc1PBRIo', 'a:1:{s:11:\"valid_until\";i:1756938404;}', 1757837264),
(51, 'laravel-cache-gWCxddJH7weAC8AP', 'a:1:{s:11:\"valid_until\";i:1756948991;}', 1758080171),
(52, 'laravel-cache-rTeHP08hAc9f2CvR', 'a:1:{s:11:\"valid_until\";i:1756956195;}', 1758161535),
(53, 'laravel-cache-fvJ0GDMSmKEI2ZRv', 'a:1:{s:11:\"valid_until\";i:1756957433;}', 1758163133),
(54, 'laravel-cache-zxJSn1qbEBHA6I4f', 'a:1:{s:11:\"valid_until\";i:1756959856;}', 1758161536),
(55, 'laravel-cache-n29mZh12uIlhjadA', 'a:1:{s:11:\"valid_until\";i:1756966653;}', 1758163173),
(56, 'laravel-cache-uh34oOC7COOFmXFB', 'a:1:{s:11:\"valid_until\";i:1756968136;}', 1758161536),
(57, 'laravel-cache-6FBqdO0Qr4hyTeTM', 'a:1:{s:11:\"valid_until\";i:1756972490;}', 1758161570),
(58, 'laravel-cache-RTnReLXQG3rom5ad', 'a:1:{s:11:\"valid_until\";i:1756984742;}', 1758163142),
(59, 'laravel-cache-lNQacDNWIalt2x5W', 'a:1:{s:11:\"valid_until\";i:1756989411;}', 1758182391),
(60, 'laravel-cache-1Dm8gWHCElMKyesP', 'a:1:{s:11:\"valid_until\";i:1756996592;}', 1758195512),
(61, 'laravel-cache-jg8zcuTFS9qlbWOe', 'a:1:{s:11:\"valid_until\";i:1757018663;}', 1758224543),
(62, 'laravel-cache-UazoxAQi2jr5YFew', 'a:1:{s:11:\"valid_until\";i:1757024287;}', 1758224527),
(63, 'laravel-cache-NVEYBa086srQ5a6B', 'a:1:{s:11:\"valid_until\";i:1757026592;}', 1758182372),
(64, 'laravel-cache-iHUsozKy8JVzbVbA', 'a:1:{s:11:\"valid_until\";i:1757052984;}', 1758236364),
(65, 'laravel-cache-cMoXWv1jFPFxb1SM', 'a:1:{s:11:\"valid_until\";i:1757057701;}', 1758244141),
(66, 'laravel-cache-m27wNrDlt7ihn7XC', 'a:1:{s:11:\"valid_until\";i:1757063253;}', 1758244173),
(67, 'laravel-cache-vXlv3pJqmYhVUbNC', 'a:1:{s:11:\"valid_until\";i:1757067624;}', 1758244164),
(68, 'laravel-cache-unx61vw344mMyoU8', 'a:1:{s:11:\"valid_until\";i:1757071576;}', 1758277636),
(69, 'laravel-cache-s09wTi8okAPvFYK3', 'a:1:{s:11:\"valid_until\";i:1757077020;}', 1758277680),
(70, 'laravel-cache-8KgXdD6DIRNmVdea', 'a:1:{s:11:\"valid_until\";i:1757125803;}', 1758236403),
(71, 'laravel-cache-RmRxJvToqt2jJ2X2', 'a:1:{s:11:\"valid_until\";i:1757140276;}', 1758277636),
(72, 'laravel-cache-sBCyHmPOGFahKgEz', 'a:1:{s:11:\"valid_until\";i:1757143915;}', 1758349975),
(73, 'laravel-cache-YYedd2F5YpDG4bee', 'a:1:{s:11:\"valid_until\";i:1757150730;}', 1758350010),
(74, 'laravel-cache-QxCBrAmECJimYB2q', 'a:1:{s:11:\"valid_until\";i:1757163998;}', 1758360458),
(75, 'laravel-cache-3TJ4B7I3eeQAP28W', 'a:1:{s:11:\"valid_until\";i:1757204591;}', 1758236351),
(76, 'laravel-cache-VahaEtUODHPQ8fLY', 'a:1:{s:11:\"valid_until\";i:1757221689;}', 1758360429),
(77, 'laravel-cache-BQWQBmLPTl3YduH9', 'a:1:{s:11:\"valid_until\";i:1757226637;}', 1758360457),
(78, 'laravel-cache-hChcgHPJJ4YcnEdy', 'a:1:{s:11:\"valid_until\";i:1757237284;}', 1758360424),
(79, 'laravel-cache-Ig8zICtutyH8C1oo', 'a:1:{s:11:\"valid_until\";i:1757241591;}', 1758236391),
(80, 'laravel-cache-aTBjLgJBQv7ToNwK', 'a:1:{s:11:\"valid_until\";i:1757247308;}', 1758236348),
(81, 'laravel-cache-yn6iN6cdHUpDdym4', 'a:1:{s:11:\"valid_until\";i:1757252779;}', 1758236359),
(82, 'laravel-cache-B12AvE752jJ8dxAp', 'a:1:{s:11:\"valid_until\";i:1757284440;}', 1758236400),
(83, 'laravel-cache-o9JngB8W7LH43jM7', 'a:1:{s:11:\"valid_until\";i:1757288090;}', 1758236390),
(84, 'laravel-cache-Oqoz0R8PxlvZtbKj', 'a:1:{s:11:\"valid_until\";i:1757296126;}', 1758236386),
(85, 'laravel-cache-AvOOOSWAFmWOHF04', 'a:1:{s:11:\"valid_until\";i:1757304306;}', 1758236346),
(86, 'laravel-cache-ZRclY2YNtV1z7P84', 'a:1:{s:11:\"valid_until\";i:1757398915;}', 1757739775);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(0) DEFAULT NULL,
  `owner` varchar(0) DEFAULT NULL,
  `expiration` varchar(0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `daurah`
--

CREATE TABLE `daurah` (
  `group_id` int NOT NULL,
  `group_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `daurah`
--

INSERT INTO `daurah` (`group_id`, `group_name`) VALUES
(1, 'Daurah 1'),
(2, 'Daurah 2'),
(3, 'Daurah 3'),
(4, 'Daurah 4'),
(5, 'Daurah 5'),
(6, 'Daurah 6'),
(7, 'Daurah 7'),
(8, 'Daurah 8');

-- --------------------------------------------------------

--
-- Table structure for table `daurah_anggota`
--

CREATE TABLE `daurah_anggota` (
  `user_id` bigint NOT NULL,
  `group_id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `daurah_anggota`
--

INSERT INTO `daurah_anggota` (`user_id`, `group_id`, `name`) VALUES
(100000000, 1, 'Abdurahman Husin Aljufri '),
(100000001, 1, 'Hasyim Shahab '),
(100000002, 1, 'Umar Ferdy Assegaf '),
(100000003, 1, 'Lutfi Attamimi '),
(100000004, 1, 'Ali Zainal Abidin Almuhdor '),
(100000005, 1, 'Alwi Aljufri '),
(100000006, 1, 'Alwi Sumith '),
(100000007, 1, 'Hasan Damis '),
(100000008, 1, 'Idrus Assegaf '),
(100000009, 1, 'Riza Baraqbah '),
(100000010, 1, 'Muhammad BSA '),
(100000011, 1, 'Zainal Abidin Binagil '),
(100000012, 1, 'Abu Bakar Cherid '),
(100000013, 1, 'Fauzi Yahya '),
(100000014, 1, 'Hasan Alaydrus '),
(100000015, 1, 'Edrus Syech Almasyhur '),
(100000016, 1, 'Abdulrahman Alaydrus '),
(100000017, 1, 'Syafiq Baridwan '),
(100000018, 1, 'Muhammad Zen Hasni '),
(100000019, 1, 'Salim Assegaf '),
(100000020, 1, 'Hasan Baraqbah '),
(100000021, 1, 'Nabil Alatas '),
(100000022, 1, 'Muhamad Alhadar '),
(100000023, 1, 'Abu Bakar Assegaf '),
(100000024, 1, 'Muhammad Talib Alatas '),
(100000025, 1, 'Ismail Assegaf '),
(100000026, 1, 'Hasan BSA '),
(100000027, 1, 'Fahmi Alatas '),
(100000028, 1, 'Husin Assegaf '),
(100000029, 1, 'Syehan Aljufri'),
(100000031, 2, 'Zein Almunawar '),
(100000032, 2, 'Novel Alhaddad '),
(100000033, 2, 'Husin Alatas '),
(100000034, 2, 'Abdullah Albar '),
(100000035, 2, 'Abdurahman Assegaf '),
(100000036, 2, 'Alwi Mauladawilah '),
(100000037, 2, 'Muhammad Izat Alhaddad '),
(100000038, 2, 'Alwi Assegaf '),
(100000039, 2, 'Syech Alhabsyi '),
(100000040, 2, 'Hamid Alhaddad '),
(100000041, 2, 'Hamzah Banahsan '),
(100000042, 2, 'Taufiq Assegaf '),
(100000043, 2, 'Addin Shahab '),
(100000044, 2, 'Anis Alkaff '),
(100000045, 2, 'Muhammad bin Thalib '),
(100000046, 2, 'Andry Handoko '),
(100000047, 2, 'Syarif Rum Assegaf '),
(100000048, 2, 'Fauzi Aljufri '),
(100000049, 2, 'Ali Baraqbah '),
(100000050, 2, 'Soleh Assegaf, dr '),
(100000051, 2, 'Noval Assegaf '),
(100000052, 2, 'Muhammad Aljufri '),
(100000053, 2, 'Muhammad Quthban '),
(100000054, 2, 'Rusdi Mauladawilah '),
(100000055, 2, 'Abdulkadir Alaydrus '),
(100000056, 2, 'Muchsin Farobi Alatas '),
(100000057, 2, 'Haidar Baraqbah '),
(100000058, 2, 'Ali Thoha Alhaddad '),
(100000059, 2, 'Sadiq Assegaf'),
(100000060, 3, 'Fauzan Ismail Alkatiri '),
(100000061, 3, 'Muhammad Fadaq '),
(100000062, 3, 'Abdullah Bin Thahir '),
(100000063, 3, 'Abdullah Alhamid '),
(100000064, 3, 'Husain Abdullah Aljufri '),
(100000065, 3, 'Alwi Assegaf Solo '),
(100000066, 3, 'Abdullah Alaydrus '),
(100000067, 3, 'Husein Soleh Baagil '),
(100000068, 3, 'Farid Attamimi '),
(100000069, 3, 'Muhammad Alhabsyi '),
(100000070, 3, ' Abdillah Alatas '),
(100000071, 3, 'Hasan Abdulkadir Alatas '),
(100000072, 3, 'M. Bagir Hamid Alhaddad '),
(100000073, 3, 'Hasan Rofik Alatas '),
(100000074, 3, 'Abu Bakar bin Yahya '),
(100000075, 3, 'Salim Alaydrus '),
(100000076, 3, ' Hadi Aljufri '),
(100000077, 3, 'Syaugi Assegaf '),
(100000078, 3, 'Hasyim Nahdi '),
(100000079, 3, 'Idrus Bajuber '),
(100000080, 3, 'Fahmi Ali Albar '),
(100000081, 3, 'Abdullah Husin Barakwan '),
(100000082, 3, 'Umar Abdullah Alhabsyi '),
(100000083, 3, 'Nafi Bakbud '),
(100000084, 3, 'Ali Ridho Alkaff '),
(100000085, 3, 'Salim Husin Barakwan '),
(100000086, 3, 'H. Wahib '),
(100000087, 3, 'H. M. Mamun '),
(100000088, 3, 'Anis Albahar '),
(100000089, 3, 'Muhammad Binagil'),
(100000090, 4, 'Toha Abdulloh Bafagih '),
(100000091, 4, 'Abdullah Salim Alatas '),
(100000092, 4, 'Budi Haidar Alatas '),
(100000093, 4, 'Muhammad Alhabsyi '),
(100000094, 4, 'Abdullah Muhammad Binagil '),
(100000095, 4, 'Ustadz Ruslani '),
(100000096, 4, 'Ahmad Abu Bakar Cherid '),
(100000097, 4, 'Jakfar Idrus Alhamid '),
(100000098, 4, 'Husin Muhammad Fadaq '),
(100000099, 4, 'Segaf Syech Assegaf '),
(100000100, 4, 'Shahabuddin Shahab '),
(100000101, 4, 'Miftah '),
(100000102, 4, 'Muchsin Syeh Alhaddad '),
(100000103, 4, 'Haidar Ahmad Assegaf '),
(100000104, 4, 'Mahdi Aljufri '),
(100000105, 4, 'Husein Salim Alatas '),
(100000106, 4, 'Haydar Muhammad Binagil '),
(100000107, 4, 'Yahya Shahab '),
(100000108, 4, 'Hasan Abdullah Alatas '),
(100000109, 4, 'Abdillah Baagil '),
(100000110, 4, 'Saleh Anis Aljufri '),
(100000111, 4, 'Bagir Ahmad Shatri '),
(100000112, 4, 'Abdurahman Alkaff '),
(100000113, 4, ' Ahmad Muhajir Binagil '),
(100000114, 4, 'Abdurrahman Abu Bakar Aljufri '),
(100000115, 4, 'Ustadz Nur Sobah '),
(100000116, 4, 'Umar Husin Alatas '),
(100000117, 4, 'Abdullah Alwi Alatas '),
(100000118, 4, 'Luthfi Alhaddad '),
(100000119, 4, 'Fikri Mustofa Al Khered'),
(100000120, 5, 'Fadil Hasan Baabud '),
(100000121, 5, 'Ustadz Muhammad Haris '),
(100000122, 5, 'Sholeh Muthohar '),
(100000123, 5, 'Ayik Firhad Alatas '),
(100000124, 5, 'Lukman Bayhakki '),
(100000125, 5, 'Hasan Abdullah Salim Yahya '),
(100000126, 5, 'Ali Umar Albar '),
(100000127, 5, 'Nizar Aidid '),
(100000128, 5, 'Muhammad Ismed Badjeber '),
(100000129, 5, 'Mustofa Alatas '),
(100000130, 5, 'Muhammad Syafik Khaniman '),
(100000131, 5, 'Rizal '),
(100000132, 5, 'Taufiq Assegaf Ciputat '),
(100000133, 5, 'Ali Alwi Aljufri '),
(100000134, 5, 'Hasan Arif Babud '),
(100000135, 5, 'Ahmad Ridho Alatas '),
(100000136, 5, 'Husein Abdullah Salim Yahya '),
(100000137, 5, 'Ahsan Yahya '),
(100000138, 5, ' Syauqi BSA '),
(100000139, 5, 'Zein Babud '),
(100000140, 5, 'Tohir Muhammad Alhaddad '),
(100000141, 5, 'Muhammad Fauzi Musawa '),
(100000142, 5, 'Muhammad Zuhair Alhaddad '),
(100000143, 5, 'Azmi Alaydrus '),
(100000144, 5, 'Muhammad Alhabsyi '),
(100000145, 5, 'Ali BSA '),
(100000146, 5, ' Hasan Salim Alatas '),
(100000147, 5, 'Muhammad Abdullah Yahya   '),
(100000148, 5, 'Abdullah Mauladawilah '),
(100000149, 5, 'Nizar Maghrobi'),
(100000150, 6, 'Muhtar Alhadar '),
(100000151, 6, 'Mukrim Nuqman Ishaaq '),
(100000152, 6, 'Farasdaq BSA '),
(100000153, 6, 'Abu Bakar Baraqbah '),
(100000154, 6, 'Muhammad Hidayat '),
(100000155, 6, 'Aang Sunarto'),
(100000156, 6, 'Abdurrahman Abdullah Assegaf '),
(100000157, 6, 'Abubakar Fuad Yamani '),
(100000158, 6, 'Ivan RM '),
(100000159, 6, 'Muhammad Marshal Habil   '),
(100000160, 6, 'Husin Abdullah Mauladawilah '),
(100000161, 6, 'Hamid Muchdar Alkaff '),
(100000162, 6, 'Zein Husein Bajamal '),
(100000163, 6, 'Abdullah Shatri '),
(100000164, 6, 'Bagir Alkaff '),
(100000165, 6, 'Ami Idrus Alaydrus '),
(100000166, 6, 'Fauzan Assegaf '),
(100000167, 6, 'Muhammad Anis Baraqbah '),
(100000168, 6, 'Zaki Khaneman '),
(100000169, 6, 'Sayid Idrus Alaydrus '),
(100000170, 6, 'Ahmad Muhsin Sumaith '),
(100000171, 6, 'H. Supriyanto '),
(100000172, 6, 'Ibrahim Alhabsyi '),
(100000173, 6, 'Muhammad Irham Badjeber '),
(100000174, 6, 'Musa Kadzim Ridho Alatas '),
(100000175, 6, 'Fadhil Alhabsyi '),
(100000176, 6, 'Ridhoi Mustaqim '),
(100000177, 6, 'Husein Muhammad Buftaim '),
(100000178, 6, 'Hasbi Asofi'),
(100000179, 7, 'Farid Assegaf'),
(100000180, 7, 'Faris Albar '),
(100000181, 7, 'Ali Akbar '),
(100000182, 7, 'Hamid Aljufri '),
(100000183, 7, 'Abdullah Abubakar '),
(100000184, 7, 'Fauzi Assegaf '),
(100000185, 7, 'Abdullah Assegaf '),
(100000186, 7, 'Ali Abdullah Badjeber '),
(100000187, 7, 'Ibrahim  Hinduan '),
(100000188, 7, 'Maulana Alaydrus '),
(100000189, 7, 'Salim BSA '),
(100000190, 7, 'H. Makmun '),
(100000191, 7, 'Muhammad Fahreza '),
(100000192, 7, 'Salim Alatas '),
(100000193, 7, 'Abdul Aziz Alatas '),
(100000194, 7, 'Idrus BSA '),
(100000195, 7, 'Abdullah BSA '),
(100000196, 7, 'Abdul Rasyid '),
(100000197, 7, 'Reza BSA '),
(100000198, 7, 'Budiman '),
(100000199, 7, 'H. Fauzi '),
(100000200, 7, 'Ahmad Haydar '),
(100000201, 7, 'Umar BSA '),
(100000202, 7, 'Ali Hasan Alatas  '),
(100000203, 7, 'Usman Munawar '),
(100000204, 7, 'Ary Alhadar '),
(100000205, 7, 'Yusuf Thoha '),
(100000206, 7, 'Novel Alhabsyi '),
(100000207, 7, 'Wiyono'),
(100000208, 7, 'Asyur Ali Bajeber'),
(100000209, 2, 'Bagir BSA'),
(100000213, 6, 'Danish'),
(250909060, 8, 'Sherin'),
(250909399, 8, 'Aang Sunarto'),
(250909696, 8, 'eric'),
(250909768, 8, 'icha');

-- --------------------------------------------------------

--
-- Table structure for table `donasi`
--

CREATE TABLE `donasi` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `rp` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `donasi`
--

INSERT INTO `donasi` (`id`, `user_id`, `tanggal`, `rp`) VALUES
(3, 100000209, '2025-08-26', 10000),
(4, 100000182, '2025-08-27', 50000),
(5, 100000213, '2025-09-04', 5000000),
(6, 100000213, '2025-09-05', 3000000);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` varchar(0) DEFAULT NULL,
  `uuid` varchar(0) DEFAULT NULL,
  `connection` varchar(0) DEFAULT NULL,
  `queue` varchar(0) DEFAULT NULL,
  `payload` varchar(0) DEFAULT NULL,
  `exception` varchar(0) DEFAULT NULL,
  `failed_at` varchar(0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `group_user`
--

CREATE TABLE `group_user` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `group_user`
--

INSERT INTO `group_user` (`id`, `name`) VALUES
(1, 'Admin'),
(2, 'Hafiz');

-- --------------------------------------------------------

--
-- Table structure for table `hapalan`
--

CREATE TABLE `hapalan` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `surah` int NOT NULL,
  `ayah` int NOT NULL,
  `audio` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_khatam`
--

CREATE TABLE `jadwal_khatam` (
  `id` int NOT NULL,
  `nama_acara` varchar(200) NOT NULL,
  `nama_tempat` varchar(200) NOT NULL,
  `waktu` date NOT NULL,
  `alamat` varchar(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jadwal_khatam`
--

INSERT INTO `jadwal_khatam` (`id`, `nama_acara`, `nama_tempat`, `waktu`, `alamat`) VALUES
(3, 'Kegiatan Khatam al Quran ke 5', 'Codet jakarta timur', '2025-09-06', 'jl. Sudirman Jakarta Selatan');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` varchar(0) DEFAULT NULL,
  `queue` varchar(0) DEFAULT NULL,
  `payload` varchar(0) DEFAULT NULL,
  `attempts` varchar(0) DEFAULT NULL,
  `reserved_at` varchar(0) DEFAULT NULL,
  `available_at` varchar(0) DEFAULT NULL,
  `created_at` varchar(0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(0) DEFAULT NULL,
  `name` varchar(0) DEFAULT NULL,
  `total_jobs` varchar(0) DEFAULT NULL,
  `pending_jobs` varchar(0) DEFAULT NULL,
  `failed_jobs` varchar(0) DEFAULT NULL,
  `failed_job_ids` varchar(0) DEFAULT NULL,
  `options` varchar(0) DEFAULT NULL,
  `cancelled_at` varchar(0) DEFAULT NULL,
  `created_at` varchar(0) DEFAULT NULL,
  `finished_at` varchar(0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `juzAlquran_urutAyah`
--

CREATE TABLE `juzAlquran_urutAyah` (
  `id` int NOT NULL,
  `juz` int NOT NULL,
  `noSurah_awal` int NOT NULL,
  `noAyah_awal` int NOT NULL,
  `noSurah_akhir` int NOT NULL,
  `noAyah_akhir` int NOT NULL,
  `globalAwal` int NOT NULL,
  `globalAkhir` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `juzAlquran_urutAyah`
--

INSERT INTO `juzAlquran_urutAyah` (`id`, `juz`, `noSurah_awal`, `noAyah_awal`, `noSurah_akhir`, `noAyah_akhir`, `globalAwal`, `globalAkhir`) VALUES
(1, 1, 1, 1, 2, 141, 1, 148),
(2, 2, 2, 142, 2, 252, 149, 259),
(3, 3, 2, 253, 3, 92, 260, 379),
(4, 4, 3, 93, 4, 23, 380, 508),
(5, 5, 4, 24, 4, 147, 509, 632),
(6, 6, 4, 148, 5, 81, 633, 742),
(7, 7, 5, 82, 6, 110, 743, 891),
(8, 8, 6, 111, 7, 87, 892, 1033),
(9, 9, 7, 88, 8, 40, 1034, 1192),
(10, 10, 8, 41, 9, 92, 1193, 1319),
(11, 11, 9, 93, 11, 5, 1320, 1470),
(12, 12, 11, 6, 12, 52, 1471, 1640),
(13, 13, 12, 53, 14, 52, 1641, 1794),
(14, 14, 14, 52, 15, 99, 1795, 2021),
(15, 15, 16, 1, 16, 128, 2022, 2206),
(16, 16, 17, 1, 18, 74, 2207, 2475),
(17, 17, 18, 75, 19, 98, 2476, 2665),
(18, 18, 20, 1, 20, 135, 2666, 2802),
(19, 19, 21, 1, 22, 78, 2803, 2944),
(20, 20, 23, 1, 24, 64, 2945, 3107),
(21, 21, 25, 1, 29, 45, 3108, 3278),
(22, 22, 29, 46, 33, 30, 3279, 3448),
(23, 23, 33, 31, 36, 27, 3449, 3623),
(24, 24, 36, 28, 39, 31, 3624, 3799),
(25, 25, 39, 32, 41, 46, 3800, 3986),
(26, 26, 41, 47, 45, 37, 3987, 4181),
(27, 27, 46, 1, 51, 30, 4182, 4580),
(28, 28, 51, 31, 57, 29, 4581, 4717),
(29, 29, 58, 1, 66, 12, 4718, 5148),
(30, 30, 67, 1, 114, 6, 5149, 6236);

-- --------------------------------------------------------

--
-- Table structure for table `khatam`
--

CREATE TABLE `khatam` (
  `id` int NOT NULL,
  `user_id` bigint NOT NULL,
  `jumlah_khatam` int NOT NULL,
  `jumlah_tidak_baca` int NOT NULL,
  `jumlah_membadalkan` int NOT NULL,
  `keterangan` varchar(250) NOT NULL,
  `tanggal` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `khatam`
--

INSERT INTO `khatam` (`id`, `user_id`, `jumlah_khatam`, `jumlah_tidak_baca`, `jumlah_membadalkan`, `keterangan`, `tanggal`) VALUES
(18, 100000201, 1, 1, 1, 'keterangan', '2000-10-10'),
(20, 100000200, 1, 1, 1, 'a', '2025-08-26');

-- --------------------------------------------------------

--
-- Table structure for table `khotmul`
--

CREATE TABLE `khotmul` (
  `id` int NOT NULL,
  `group_id` int NOT NULL,
  `juz` int NOT NULL,
  `periode` int NOT NULL,
  `tanggal` date NOT NULL,
  `anggota_id` int DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `khotmul`
--

INSERT INTO `khotmul` (`id`, `group_id`, `juz`, `periode`, `tanggal`, `anggota_id`, `status`) VALUES
(1111, 6, 1, 1, '2025-09-01', 100000150, ''),
(1112, 6, 2, 1, '2025-09-01', 100000151, ''),
(1113, 6, 3, 1, '2025-09-01', 100000152, ''),
(1114, 6, 4, 1, '2025-09-01', 100000153, ''),
(1115, 6, 5, 1, '2025-09-01', 100000154, ''),
(1116, 6, 6, 1, '2025-09-01', 100000155, ''),
(1117, 6, 7, 1, '2025-09-01', 100000156, ''),
(1118, 6, 8, 1, '2025-09-01', 100000157, ''),
(1119, 6, 9, 1, '2025-09-01', 100000158, ''),
(1120, 6, 10, 1, '2025-09-01', 100000159, ''),
(1121, 6, 11, 1, '2025-09-01', 100000160, ''),
(1122, 6, 12, 1, '2025-09-01', 100000161, ''),
(1123, 6, 13, 1, '2025-09-01', 100000162, ''),
(1124, 6, 14, 1, '2025-09-01', 100000163, ''),
(1125, 6, 15, 1, '2025-09-01', 100000164, ''),
(1126, 6, 16, 1, '2025-09-01', 100000165, ''),
(1127, 6, 17, 1, '2025-09-01', 100000166, ''),
(1128, 6, 18, 1, '2025-09-01', 100000167, ''),
(1129, 6, 19, 1, '2025-09-01', 100000168, ''),
(1130, 6, 20, 1, '2025-09-01', 100000169, ''),
(1131, 6, 21, 1, '2025-09-01', 100000170, ''),
(1132, 6, 22, 1, '2025-09-01', 100000171, ''),
(1133, 6, 23, 1, '2025-09-01', 100000172, ''),
(1134, 6, 24, 1, '2025-09-01', 100000173, ''),
(1135, 6, 25, 1, '2025-09-01', 100000174, ''),
(1136, 6, 26, 1, '2025-09-01', 100000175, ''),
(1137, 6, 27, 1, '2025-09-01', 100000176, ''),
(1138, 6, 28, 1, '2025-09-01', 100000177, 'send_no'),
(1139, 6, 29, 1, '2025-09-01', 100000178, 'send_no'),
(1140, 6, 30, 1, '2025-09-01', 100000213, 'send_voice'),
(1142, 7, 1, 1, '2025-09-01', 100000179, ''),
(1143, 7, 2, 1, '2025-09-01', 100000180, ''),
(1144, 7, 3, 1, '2025-09-01', 100000181, ''),
(1145, 7, 4, 1, '2025-09-01', 100000182, ''),
(1146, 7, 5, 1, '2025-09-01', 100000183, ''),
(1147, 7, 6, 1, '2025-09-01', 100000184, ''),
(1148, 7, 7, 1, '2025-09-01', 100000185, ''),
(1149, 7, 8, 1, '2025-09-01', 100000186, ''),
(1150, 7, 9, 1, '2025-09-01', 100000187, ''),
(1151, 7, 10, 1, '2025-09-01', 100000188, ''),
(1152, 7, 11, 1, '2025-09-01', 100000189, ''),
(1153, 7, 12, 1, '2025-09-01', 100000190, ''),
(1154, 7, 13, 1, '2025-09-01', 100000191, ''),
(1155, 7, 14, 1, '2025-09-01', 100000192, ''),
(1156, 7, 15, 1, '2025-09-01', 100000193, ''),
(1157, 7, 16, 1, '2025-09-01', 100000194, ''),
(1158, 7, 17, 1, '2025-09-01', 100000195, ''),
(1159, 7, 18, 1, '2025-09-01', 100000196, ''),
(1160, 7, 19, 1, '2025-09-01', 100000197, ''),
(1161, 7, 20, 1, '2025-09-01', 100000198, ''),
(1162, 7, 21, 1, '2025-09-01', 100000199, ''),
(1163, 7, 22, 1, '2025-09-01', 100000200, ''),
(1164, 7, 23, 1, '2025-09-01', 100000201, ''),
(1165, 7, 24, 1, '2025-09-01', 100000202, ''),
(1166, 7, 25, 1, '2025-09-01', 100000203, ''),
(1167, 7, 26, 1, '2025-09-01', 100000204, ''),
(1168, 7, 27, 1, '2025-09-01', 100000205, ''),
(1169, 7, 28, 1, '2025-09-01', 100000206, ''),
(1170, 7, 29, 1, '2025-09-01', 100000207, ''),
(1171, 7, 30, 1, '2025-09-01', 100000208, ''),
(1322, 8, 1, 1, '2025-09-09', 250909060, NULL),
(1323, 8, 2, 1, '2025-09-09', 250909696, 'send_voice'),
(1324, 8, 3, 1, '2025-09-09', 250909768, NULL),
(1325, 8, 4, 1, '2025-09-09', 250909399, NULL),
(1326, 8, 5, 1, '2025-09-09', NULL, NULL),
(1327, 8, 6, 1, '2025-09-09', NULL, NULL),
(1328, 8, 7, 1, '2025-09-09', NULL, NULL),
(1329, 8, 8, 1, '2025-09-09', NULL, NULL),
(1330, 8, 9, 1, '2025-09-09', NULL, NULL),
(1331, 8, 10, 1, '2025-09-09', NULL, NULL),
(1332, 8, 11, 1, '2025-09-09', NULL, NULL),
(1333, 8, 12, 1, '2025-09-09', NULL, NULL),
(1334, 8, 13, 1, '2025-09-09', NULL, NULL),
(1335, 8, 14, 1, '2025-09-09', NULL, NULL),
(1336, 8, 15, 1, '2025-09-09', NULL, NULL),
(1337, 8, 16, 1, '2025-09-09', NULL, NULL),
(1338, 8, 17, 1, '2025-09-09', NULL, NULL),
(1339, 8, 18, 1, '2025-09-09', NULL, NULL),
(1340, 8, 19, 1, '2025-09-09', NULL, NULL),
(1341, 8, 20, 1, '2025-09-09', NULL, NULL),
(1342, 8, 21, 1, '2025-09-09', NULL, NULL),
(1343, 8, 22, 1, '2025-09-09', NULL, NULL),
(1344, 8, 23, 1, '2025-09-09', NULL, NULL),
(1345, 8, 24, 1, '2025-09-09', NULL, NULL),
(1346, 8, 25, 1, '2025-09-09', NULL, NULL),
(1347, 8, 26, 1, '2025-09-09', NULL, NULL),
(1348, 8, 27, 1, '2025-09-09', NULL, NULL),
(1349, 8, 28, 1, '2025-09-09', NULL, NULL),
(1350, 8, 29, 1, '2025-09-09', NULL, NULL),
(1351, 8, 30, 1, '2025-09-09', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `khotmul_periode`
--

CREATE TABLE `khotmul_periode` (
  `id` int NOT NULL,
  `periode` int NOT NULL,
  `group_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `khotmul_periode`
--

INSERT INTO `khotmul_periode` (`id`, `periode`, `group_id`, `tanggal`, `keterangan`) VALUES
(1, 1, 1, '2025-09-01', 'ok');

-- --------------------------------------------------------

--
-- Table structure for table `khotmul_rec`
--

CREATE TABLE `khotmul_rec` (
  `id` int NOT NULL,
  `khotmul_id` int DEFAULT NULL,
  `periode_id` int DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `path` varchar(100) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size` varchar(100) NOT NULL,
  `juz` int DEFAULT NULL,
  `surah` int DEFAULT NULL,
  `ayah_awal` int DEFAULT NULL,
  `ayah_akhir` int DEFAULT NULL,
  `anggota_id` int DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `catatan` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `khotmul_rec`
--

INSERT INTO `khotmul_rec` (`id`, `khotmul_id`, `periode_id`, `name`, `path`, `mime_type`, `size`, `juz`, `surah`, `ayah_awal`, `ayah_akhir`, `anggota_id`, `group_id`, `catatan`) VALUES
(21, 1140, NULL, 'record_1756971271131.m4a', 'files/zsNYk5Tg7TLeSzx6rAs8wF4gEeSfLHjuCCyitzeN.mp4', 'application/octet-stream', '31253', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 1139, NULL, 'record_1756971286712.m4a', 'files/usP4z6kAvxWLReui8jLmm08p552uQWxPUAwYmClr.mp4', 'application/octet-stream', '30235', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 1138, NULL, 'record_1756971309477.m4a', 'files/euZwEBLVlSge0dXBJ6LjLVnOcyAoD6ZzxTCI3GOv.mp4', 'application/octet-stream', '30239', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 1138, NULL, 'record_1756971327941.m4a', 'files/qZllMr16g7spp9cNUCJ9dWnnO0zfXH6EexHMFRBC.mp4', 'application/octet-stream', '24984', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` tinyint DEFAULT NULL,
  `migration` varchar(36) DEFAULT NULL,
  `batch` tinyint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(NULL, '2025_08_11_102342_create_posts_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(0) DEFAULT NULL,
  `token` varchar(0) DEFAULT NULL,
  `created_at` varchar(0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `title`, `content`, `created_at`, `updated_at`) VALUES
(1, 'trest', 'sd', '2025-08-31 21:55:05', '2025-08-17 21:55:05');

-- --------------------------------------------------------

--
-- Table structure for table `quran_text`
--

CREATE TABLE `quran_text` (
  `id` int NOT NULL,
  `juz` int NOT NULL,
  `surah` int NOT NULL,
  `ayah` int NOT NULL,
  `text` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quran_text`
--

INSERT INTO `quran_text` (`id`, `juz`, `surah`, `ayah`, `text`) VALUES
(1, 1, 1, 1, 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ'),
(2, 1, 1, 2, 'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ'),
(3, 1, 1, 3, 'الرَّحْمَٰنِ الرَّحِيمِ'),
(4, 1, 1, 4, 'مَالِكِ يَوْمِ الدِّينِ'),
(5, 1, 1, 5, 'إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ'),
(6, 1, 1, 6, 'اهْدِنَا الصِّرَاطَ الْمُسْتَقِيمَ'),
(7, 1, 1, 7, 'صِرَاطَ الَّذِينَ أَنْعَمْتَ عَلَيْهِمْ ...'),
(8, 1, 2, 1, 'الم'),
(9, 1, 2, 2, 'ذَٰلِكَ الْكِتَابُ لَا رَيْبَ فِيهِ ...');

-- --------------------------------------------------------

--
-- Table structure for table `reward`
--

CREATE TABLE `reward` (
  `id` int NOT NULL,
  `anggota_id` int NOT NULL,
  `keterangan` varchar(200) DEFAULT NULL,
  `rp` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(0) DEFAULT NULL,
  `user_id` varchar(0) DEFAULT NULL,
  `ip_address` varchar(0) DEFAULT NULL,
  `user_agent` varchar(0) DEFAULT NULL,
  `payload` varchar(0) DEFAULT NULL,
  `last_activity` varchar(0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `sqlite_sequence`
--

CREATE TABLE `sqlite_sequence` (
  `name` varchar(10) DEFAULT NULL,
  `seq` tinyint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `sqlite_sequence`
--

INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES
('migrations', 3);

-- --------------------------------------------------------

--
-- Table structure for table `surah_index`
--

CREATE TABLE `surah_index` (
  `no_surah` int NOT NULL,
  `nama_surah` varchar(100) DEFAULT NULL,
  `urutan_global_awal` int DEFAULT NULL,
  `urutan_global_akhir` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `surah_index`
--

INSERT INTO `surah_index` (`no_surah`, `nama_surah`, `urutan_global_awal`, `urutan_global_akhir`) VALUES
(1, 'Al-Fatihah', 1, 7),
(2, 'Al-Baqarah', 8, 293),
(3, 'Ali Imran', 294, 493),
(4, 'An-Nisa', 494, 669),
(5, 'Al-Maidah', 670, 789),
(6, 'Al-Anam', 790, 954),
(7, 'Al-Araf', 955, 1161),
(8, 'Al-Anfal', 1162, 1236),
(9, 'At-Taubah', 1237, 1364),
(10, 'Yunus', 1365, 1475),
(11, 'Hud', 1476, 1595),
(12, 'Yusuf', 1596, 1706),
(13, 'Ar-Rad', 1707, 1749),
(14, 'Ibrahim', 1750, 1805),
(15, 'Al-Hijr', 1806, 1850),
(16, 'An-Nahl', 1851, 1965),
(17, 'Al-Isra', 1966, 2050),
(18, 'Al-Kahf', 2051, 2140),
(19, 'Maryam', 2141, 2215),
(20, 'Taha', 2216, 2293),
(21, 'Al-Anbiya', 2294, 2365),
(22, 'Al-Hajj', 2366, 2462),
(23, 'Al-Muminun', 2463, 2530),
(24, 'An-Nur', 2531, 2595),
(25, 'Al-Furqan', 2596, 2635),
(26, 'Asy-Syuara', 2636, 2706),
(27, 'An-Naml', 2707, 2791),
(28, 'Al-Qasas', 2792, 2861),
(29, 'Al-Ankabut', 2862, 2930),
(30, 'Ar-Rum', 2931, 2989),
(31, 'Luqman', 2990, 3020),
(32, 'As-Sajdah', 3021, 3051),
(33, 'Al-Ahzab', 3052, 3118),
(34, 'Saba', 3119, 3176),
(35, 'Fatir', 3177, 3224),
(36, 'Yasin', 3225, 3276),
(37, 'As-Saffat', 3277, 3344),
(38, 'Sad', 3345, 3382),
(39, 'Az-Zumar', 3383, 3465),
(40, 'Ghafir', 3466, 3554),
(41, 'Fussilat', 3555, 3614),
(42, 'Asy-Syura', 3615, 3672),
(43, 'Az-Zukhruf', 3673, 3732),
(44, 'Ad-Dukhan', 3733, 3761),
(45, 'Al-Jasiyah', 3762, 3790),
(46, 'Al-Ahqaf', 3791, 3823),
(47, 'Muhammad', 3824, 3863),
(48, 'Al-Fath', 3864, 3906),
(49, 'Al-Hujurat', 3907, 3929),
(50, 'Qaf', 3930, 3967),
(51, 'Az-Zariyat', 3968, 4010),
(52, 'At-Tur', 4011, 4049),
(53, 'An-Najm', 4050, 4090),
(54, 'Al-Qamar', 4091, 4124),
(55, 'Ar-Rahman', 4125, 4175),
(56, 'Al-Waqiah', 4176, 4220),
(57, 'Al-Hadid', 4221, 4276),
(58, 'Al-Mujadila', 4277, 4313),
(59, 'Al-Hasyr', 4314, 4351),
(60, 'Al-Mumtahanah', 4352, 4374),
(61, 'As-Saff', 4375, 4395),
(62, 'Al-Jumua', 4396, 4408),
(63, 'Al-Munafiqun', 4409, 4420),
(64, 'At-Tagabun', 4421, 4443),
(65, 'At-Talaq', 4444, 4465),
(66, 'At-Tahrim', 4466, 4481),
(67, 'Al-Mulk', 4482, 4509),
(68, 'Al-Qalam', 4510, 4542),
(69, 'Al-Haqqah', 4543, 4571),
(70, 'Al-Maarij', 4572, 4599),
(71, 'Nuh', 4600, 4626),
(72, 'Al-Jinn', 4627, 4654),
(73, 'Al-Muzzammil', 4655, 4675),
(74, 'Al-Muddathir', 4676, 4715),
(75, 'Al-Qiyamah', 4716, 4739),
(76, 'Al-Insan', 4740, 4765),
(77, 'Al-Mursalat', 4766, 4792),
(78, 'An-Naba', 4793, 4841),
(79, 'An-Naziat', 4842, 4881),
(80, 'Abasa', 4882, 4911),
(81, 'At-Takwir', 4912, 4932),
(82, 'Al-Infitar', 4933, 4944),
(83, 'Al-Mutaffifin', 4945, 4976),
(84, 'Al-Insyiqaq', 4977, 4995),
(85, 'Al-Buruj', 4996, 5015),
(86, 'At-Tariq', 5016, 5027),
(87, 'Al-Ala', 5028, 5046),
(88, 'Al-Gasyiyah', 5047, 5072),
(89, 'Al-Fajr', 5073, 5100),
(90, 'Al-Balad', 5101, 5119),
(91, 'Asy-Syams', 5120, 5131),
(92, 'Al-Lail', 5132, 5149),
(93, 'Ad-Duha', 5150, 5161),
(94, 'Asy-Syarh', 5162, 5169),
(95, 'At-Tin', 5170, 5177),
(96, 'Al-Alaq', 5178, 5189),
(97, 'Al-Qadr', 5190, 5194),
(98, 'Al-Bayyinah', 5195, 5202),
(99, 'Az-Zalzalah', 5203, 5210),
(100, 'Al-Adiyat', 5211, 5221),
(101, 'Al-Qariah', 5222, 5231),
(102, 'At-Takasur', 5232, 5239),
(103, 'Al-Asr', 5240, 5242),
(104, 'Al-Humazah', 5243, 5252),
(105, 'Al-Fil', 5253, 5257),
(106, 'Quraisy', 5258, 5261),
(107, 'Al-Maun', 5262, 5268),
(108, 'Al-Kausar', 5269, 5271),
(109, 'Al-Kafirun', 5272, 5277),
(110, 'An-Nasr', 5278, 5280),
(111, 'Al-Lahab', 5281, 5285),
(112, 'Al-Ikhlas', 5286, 5289),
(113, 'Al-Falaq', 5290, 5294),
(114, 'An-Nas', 5295, 6236);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anggota_id` bigint DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `anggota_id`, `group_id`, `created_at`, `updated_at`) VALUES
(6, 'wiyono', 'admin@pusaka-ilahi.com', NULL, '$2y$12$affB.v90lN.jkrpM5yQgbeFnAntCfxRJ1BqSawbnMOacvkz1zy2x.', NULL, 100000207, 1, '2025-08-19 14:28:16', '2025-08-19 14:28:16'),
(11, 'Asyur Ali Bajeber', 'asyur@gmail.com', NULL, '$2y$12$nq3bzni.9B.Lwn.XzmljpuTrcA5NeWvkUZ4f4znP1xDHfK3xUT1a2', NULL, 100000208, 2, '2025-08-25 08:21:59', '2025-09-04 01:59:51'),
(12, 'Bagir BSA', 'bagir@gmail.com', NULL, '$2y$12$O5Jycq5qBpjUe5z9EfQ.XO32QD7HpBpQVHjrp/EHTAS2NIXsMwaDW', NULL, 100000209, 2, '2025-08-25 10:05:37', '2025-08-31 23:42:30'),
(14, 'eris', 'eristaufiq@gmail.com', NULL, '$2y$12$T65PdIY1MKkQJyiuPtTSWu8xURvHNIq3JQTZbDPHkGWx5JxLxeHwi', NULL, NULL, 1, '2025-08-31 06:57:21', '2025-08-31 06:57:21'),
(15, 'Hasbi Asofi', 'hasbi@gmail.com', NULL, '$2y$12$oQkqAylSMqb31H4Vf4EGRe7OssOSu0Sz/LzBBX7aBSBL/ApUgeW9.', NULL, 100000178, NULL, '2025-09-04 02:04:18', '2025-09-04 02:04:18'),
(16, 'Danish', 'danish@gmail.com', NULL, '$2y$12$mzq15linJgSF2omfs0TF.uMEOqg4De2OjdnBaWFtXNvpJMLtbiWjq', NULL, 100000213, NULL, '2025-09-04 02:05:02', '2025-09-04 02:05:02'),
(17, 'Syehan Aljufri', 'hafiz@gmail.com', NULL, '$2y$12$vb7HDfU.Awaa57CHaeDnHO6XKYZOI8ErgKtRAkRWrkVRI8r9FATGa', NULL, 100000029, NULL, '2025-09-08 07:44:27', '2025-09-08 07:44:27'),
(42, 'Sherin', 'sherin@gmail.com', NULL, '$2y$12$kLw4hqe1jCjL4DZU924fA.Q.9wrE0FF/Oh.Z6NIxnnXpbRNKJA6py', NULL, 250909060, 2, '2025-09-09 04:14:49', '2025-09-09 04:14:49'),
(43, 'eric', 'eric@gmail.com', NULL, '$2y$12$6CesX0hhvatwAY6fFBjGJ.vN/TjPuw/ZpVlM4tLT47x.SL/Erqad6', NULL, 250909696, 2, '2025-09-09 04:28:16', '2025-09-09 04:28:16'),
(44, 'icha', 'icha@gmail.com', NULL, '$2y$12$e3gJwVTDhKH.BdNda0dxoeligPGOvqGFOqcLxtVh81qbqo1Z0ILnq', NULL, 250909768, 2, '2025-09-09 04:46:01', '2025-09-09 04:46:01'),
(45, 'Aang Sunarto', 'aangsun@gmail.com', NULL, '$2y$12$CUy/Td9.jARd3zGFOEfoee/FYoz/lIiRsk5XgaKnuEzZuppSkzpTa', NULL, 250909399, 2, '2025-09-09 06:27:12', '2025-09-09 06:27:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ayahharian`
--
ALTER TABLE `ayahharian`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daurah`
--
ALTER TABLE `daurah`
  ADD PRIMARY KEY (`group_id`);

--
-- Indexes for table `daurah_anggota`
--
ALTER TABLE `daurah_anggota`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `donasi`
--
ALTER TABLE `donasi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_user`
--
ALTER TABLE `group_user`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hapalan`
--
ALTER TABLE `hapalan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jadwal_khatam`
--
ALTER TABLE `jadwal_khatam`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `juzAlquran_urutAyah`
--
ALTER TABLE `juzAlquran_urutAyah`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `khatam`
--
ALTER TABLE `khatam`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `khotmul`
--
ALTER TABLE `khotmul`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `khotmul_periode`
--
ALTER TABLE `khotmul_periode`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `khotmul_rec`
--
ALTER TABLE `khotmul_rec`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quran_text`
--
ALTER TABLE `quran_text`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reward`
--
ALTER TABLE `reward`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `surah_index`
--
ALTER TABLE `surah_index`
  ADD PRIMARY KEY (`no_surah`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ayahharian`
--
ALTER TABLE `ayahharian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `cache`
--
ALTER TABLE `cache`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `daurah`
--
ALTER TABLE `daurah`
  MODIFY `group_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `daurah_anggota`
--
ALTER TABLE `daurah_anggota`
  MODIFY `user_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250909851;

--
-- AUTO_INCREMENT for table `donasi`
--
ALTER TABLE `donasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `group_user`
--
ALTER TABLE `group_user`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hapalan`
--
ALTER TABLE `hapalan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jadwal_khatam`
--
ALTER TABLE `jadwal_khatam`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `juzAlquran_urutAyah`
--
ALTER TABLE `juzAlquran_urutAyah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `khatam`
--
ALTER TABLE `khatam`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `khotmul`
--
ALTER TABLE `khotmul`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1352;

--
-- AUTO_INCREMENT for table `khotmul_periode`
--
ALTER TABLE `khotmul_periode`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `khotmul_rec`
--
ALTER TABLE `khotmul_rec`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quran_text`
--
ALTER TABLE `quran_text`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reward`
--
ALTER TABLE `reward`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
