DROP TABLE IF EXISTS `contacts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `messages`;

CREATE TABLE `users` (
                         `user_id` int NOT NULL AUTO_INCREMENT,
                         `unique_id` int NOT NULL,
                         `fname` varchar(255) NOT NULL,
                         `lname` varchar(255) NOT NULL,
                         `email` varchar(255) NOT NULL,
                         `password` varchar(255) NOT NULL,
                         `img` varchar(400) NOT NULL,
                         `status` varchar(255) NOT NULL,
                         PRIMARY KEY (`user_id`),
                         UNIQUE KEY `unique_id` (`unique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


CREATE TABLE `contacts` (
  `contactID` int NOT NULL AUTO_INCREMENT,
  `user1_ID` int DEFAULT NULL,
  `user2_ID` int DEFAULT NULL,
  PRIMARY KEY (`contactID`),
  KEY `user1_ID` (`user1_ID`),
  KEY `user2_ID` (`user2_ID`),
  CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`user1_ID`) REFERENCES `users` (`unique_id`),
  CONSTRAINT `contacts_ibfk_2` FOREIGN KEY (`user2_ID`) REFERENCES `users` (`unique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `messages` (
                            `msg_id` int NOT NULL AUTO_INCREMENT,
                            `sender_id` int NOT NULL,
                            `receiver_id` int NOT NULL,
                            `msg` varchar(1000) NOT NULL,
                            `created_at` int NOT NULL,
                            PRIMARY KEY (`msg_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `users` VALUES (1,1134750038,'Shehryar','Ahmed','sharyarahmed4567@gmail.com','Thomas','https://notjustdev-dummy.s3.us-east-2.amazonaws.com/avatars/1.jpg','Active Now'),
                           (27,288862539,'Hamza','Aamer','Hmz@gmail.com','Lelouch','https://notjustdev-dummy.s3.us-east-2.amazonaws.com/avatars/1.jpg','Active Now'),
                           (28,1366420510,'Areeb','Tariq','areeb23@gmail.com','power23','https://notjustdev-dummy.s3.us-east-2.amazonaws.com/avatars/1.jpg','Active Now'),
                           (29,32255500,'Spali','Aman','Sparli69@gmail.com','music69','https://notjustdev-dummy.s3.us-east-2.amazonaws.com/avatars/1.jpg','Active Now'),
                           (35,69064288,'Abdulsalam','Khan','Abdul@outlook.com','cricket','https://notjustdev-dummy.s3.us-east-2.amazonaws.com/avatars/1.jpg','Active Now'),
                           (36,1277802488,'Sheri','Xhyz','sharyarahmedxyz@gmail.com','Thomas','img.jpg','Active Now');

INSERT INTO `contacts` VALUES (1,69064288,288862539),
                              (2,69064288,1134750038),
                              (3,1134750038,288862539),
                              (4,1134750038,1366420510),
                              (5,1134750038,32255500);

INSERT INTO `messages` VALUES (1,1134750038,288862539,'Tesn messague',1645380777),
                              (2,1134750038,1366420510,'From shery to areeb',1645382355),
                              (3,1134750038,1366420510,'From shery te areeb 2',1645382370),
                              (4,288862539,1134750038,'Hello',1645384894),
                              (5,288862539,1366420510,'Hey there',1645384904),
                              (6,1366420510,1134750038,'Yeah whatever',1645385007),
                              (7,1134750038,32255500,'Hello sparli',1645386634),
                              (9,32255500,1134750038,'Oye ye kiya? ',1645386724),
                              (10,1134750038,32255500,':D',1645386752),
                              (11,1134750038,1366420510,'Another message ',1645637339),
                              (25,69064288,288862539,'Ggg',1646414085),
                              (26,69064288,288862539,'Gggy',1646414859),
                              (27,69064288,288862539,'Yy',1646414915),
                              (30,69064288,1134750038,'Hello',1646416237),
                              (31,1134750038,288862539,'Helo',1647170970),
                              (32,1134750038,1366420510,'Yes',1647171006),
                              (33,1134750038,32255500,'Test',1647171017);
