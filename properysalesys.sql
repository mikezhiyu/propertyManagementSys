-- phpMyAdmin SQL Dump
-- version 4.6.5.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 21, 2017 at 11:57 PM
-- Server version: 10.1.21-MariaDB
-- PHP Version: 7.1.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `properysalesys`
--

-- --------------------------------------------------------

--
-- Table structure for table `houses`
--

CREATE TABLE `houses` (
  `id` int(11) NOT NULL,
  `ownerId` int(11) NOT NULL,
  `postCode` varchar(10) NOT NULL,
  `address` varchar(500) NOT NULL,
  `city` varchar(50) NOT NULL,
  `phoneNumber` varchar(15) NOT NULL,
  `numberOfBedroom` int(11) NOT NULL,
  `Price` decimal(10,2) NOT NULL,
  `yearOfBuild` year(4) NOT NULL,
  `propertyType` enum('detached','semi-detached','townhouse','condo') NOT NULL DEFAULT 'detached',
  `area` decimal(6,2) NOT NULL,
  `status` enum('available','sold','','') NOT NULL DEFAULT 'available',
  `description` varchar(2000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

--
-- Dumping data for table `houses`
--

INSERT INTO `houses` (`id`, `ownerId`, `postCode`, `address`, `city`, `phoneNumber`, `numberOfBedroom`, `Price`, `yearOfBuild`, `propertyType`, `area`, `status`, `description`) VALUES
(3, 1, 'H4H1B3', '1240 rue du fort', 'Montreal', '514-649-4343', 1, '130000.00', 1999, 'condo', '1500.00', 'sold', 'Inspired by the architecture of Vieux-Québec, this newly renovated & decorated studio with gas fireplace has a superb view of the fountain & the Place des Voyageurs. South-facing common terrace, year-round salt water hot tub, gym, pool, sauna, bistro and coffee shop. Direct access to ski slopes via the Cabriolet.'),
(7, 1, 'H3H1J2', '3555 Monkland', 'Montreal', '514-324-7645', 4, '80000.00', 2000, 'condo', '850.00', 'sold', '2 bedrooms Condo \"La pinède\" located 3 km of the Mount Sutton ski hill that offers a shuttle service to the mountain and less than one kilometer from the village. Private lake 2 hérons access, to skate in winter or summer swimming.'),
(11, 2, 'H5H1H3', '5434 stanly', 'Montreal', '514-654-3434', 4, '130000.00', 1999, 'detached', '1400.00', 'sold', 'Come and discover one of the most superb building site near Sutton village. The lot gives access to 80 additional acres in co-ownership with trails and swimming pond. Located on a dead end road amidst a prestine forest. Perfect for all nature lovers.'),
(15, 2, 'H4H3L2', '4554 saint Hubert', 'Montreal', '514-765-3423', 4, '130000.00', 1999, 'townhouse', '450.00', 'sold', 'A real property of yesteryear with all the charm enhanced by its renovations carried out over the years. A spectacular view of Lake Témiscouata that you can admire from the very large patio. 4 bedrooms, a bathroom and a shower room. Basement with outside exit on a large terrace and detached garage.'),
(19, 3, 'H7J4J5', '4534 krikland', 'Montreal', '514-349-6865', 4, '400000.00', 2002, 'condo', '3400.00', 'sold', 'Condo 4½ de 1000pc à 20 minutes des ponts et à proximité de tous les services. Douche indépendante et bain podium. Patio en béton 10 X 10 pieds et rangement extérieur avec 2 stationnements. Frais de notaire inclus. À qui la chance!'),
(23, 3, 'H6H1K2', '8756 macckey', 'Montreal', '514-765-2345', 6, '215000.00', 1999, 'detached', '5000.00', 'sold', 'Cozy country house on a plot of 13999 square feet.This property is adjacent to 1931-1931A Lakeshore (same owner MLS: 15458727) .Ideally sold together these 2 properties offer; 1 house, 1 Swiss chalet, 1 double garage + s. Of games on the floor and 1 big shed (for boat, vr, ...) The whole offered to 215000 $ TO SEE!');

-- --------------------------------------------------------

--
-- Table structure for table `imagepaths`
--

CREATE TABLE `imagepaths` (
  `id` int(11) NOT NULL,
  `houseId` int(11) NOT NULL,
  `imagePath` varchar(150) NOT NULL,
  `imageMimeType` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

--
-- Dumping data for table `imagepaths`
--

INSERT INTO `imagepaths` (`id`, `houseId`, `imagePath`, `imageMimeType`) VALUES
(2, 3, 'uploads/house1.4.jpeg', 'image/jpeg'),
(3, 3, 'uploads/house1.1.jpeg', 'image/jpeg'),
(4, 3, 'uploads/house1.2.jpeg', 'image/jpeg'),
(5, 3, 'uploads/house1.3.jpeg', 'image/jpeg'),
(6, 7, 'uploads/house2.1.jpeg', 'image/jpeg'),
(7, 7, 'uploads/house2.2.jpeg', 'image/jpeg'),
(8, 7, 'uploads/house2.3.jpeg', 'image/jpeg'),
(9, 7, 'uploads/house2.4.jpeg', 'image/jpeg'),
(10, 11, 'uploads/house3.1.jpeg', 'image/jpeg'),
(11, 11, 'uploads/house3.2.jpeg', 'image/jpeg'),
(12, 11, 'uploads/house3.3.jpeg', 'image/jpeg'),
(13, 11, 'uploads/house3.4.jpeg', 'image/jpeg'),
(14, 15, 'uploads/house4.1.jpeg', 'image/jpeg'),
(15, 15, 'uploads/house4.2.jpeg', 'image/jpeg'),
(16, 15, 'uploads/house4.3.jpeg', 'image/jpeg'),
(17, 15, 'uploads/house4.4.jpeg', 'image/jpeg'),
(18, 19, 'uploads/house5.1.jpeg', 'image/jpeg'),
(19, 19, 'uploads/house5.2.jpeg', 'image/jpeg'),
(20, 19, 'uploads/house5.3.jpeg', 'image/jpeg'),
(21, 19, 'uploads/house5.4.jpeg', 'image/jpeg'),
(22, 23, 'uploads/house6.1.jpeg', 'image/jpeg'),
(23, 23, 'uploads/house6.2.jpeg', 'image/jpeg'),
(24, 23, 'uploads/house6.3.jpeg', 'image/jpeg'),
(25, 23, 'uploads/house6.4.jpeg', 'image/jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(250) NOT NULL,
  `password` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `name`) VALUES
(1, 'chen@c.c', 'pop1234', 'Chen Chen'),
(2, 'Yu@zhi.com', 'Jac12345', 'Yuzhi'),
(3, 'flavie@g.com', 'Jac12345', 'flavie');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `houses`
--
ALTER TABLE `houses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ownerId` (`ownerId`);

--
-- Indexes for table `imagepaths`
--
ALTER TABLE `imagepaths`
  ADD PRIMARY KEY (`id`),
  ADD KEY `houseId` (`houseId`),
  ADD KEY `houseId_2` (`houseId`);

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
-- AUTO_INCREMENT for table `houses`
--
ALTER TABLE `houses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;
--
-- AUTO_INCREMENT for table `imagepaths`
--
ALTER TABLE `imagepaths`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `houses`
--
ALTER TABLE `houses`
  ADD CONSTRAINT `houses_ibfk_1` FOREIGN KEY (`ownerId`) REFERENCES `users` (`id`);

--
-- Constraints for table `imagepaths`
--
ALTER TABLE `imagepaths`
  ADD CONSTRAINT `imagepaths_ibfk_1` FOREIGN KEY (`houseId`) REFERENCES `houses` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
