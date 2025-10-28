drop database if exists omni;

CREATE DATABASE omni;
USE omni;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    year VARCHAR(5),
    school VARCHAR(100),
    major VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user',
    club VARCHAR(100)
);

-- create events
CREATE TABLE IF NOT EXISTS events (
    title           VARCHAR(128) NOT NULL,
    category        VARCHAR(128) NOT NULL,
    date            DATE NOT NULL,
    start_time      VARCHAR(128) NOT NULL,
    end_time        VARCHAR(128) NOT NULL,
    location        VARCHAR(128) NOT NULL,
    picture         VARCHAR(128) NOT NULL,
    startISO        VARCHAR(128) NOT NULL,
    endISO          VARCHAR(128) NOT NULL,
    PRIMARY KEY(title)
);

INSERT INTO events VALUES (
    'HackSMU: 24-Hour Hackathon',
    'tech',
    '2025-12-05',
    '7:00 PM',
    '7:00 PM',
    'SIS Building',
    'pictures/hackathon.png',
    '2025-12-05T19:00:00+08:00',
    '2025-12-06T19:00:00+08:00'
);

INSERT INTO events VALUES (
    'Open Mic & Poetry Slam',
    'arts',
    '2025-12-12',
    '8:00 PM',
    '10:30 PM',
    'Concourse Hall',
    'pictures/mic.jpeg',
    '2025-12-12T20:00:00+08:00',
    '2025-12-12T22:30:00+08:00'
);

INSERT INTO events VALUES (
    'Finance Forum: Markets 2025',
    'career',
    '2025-12-10',
    '5:30 PM',
    '7:30 PM',
    'Shaw Alumni House',
    'pictures/finance.png',
    '2025-12-10T17:30:00+08:00',
    '2025-12-10T19:30:00+08:00'
);

INSERT INTO events VALUES (
    'Skatathon 2025',
    'sports',
    '2025-12-19',
    '7:00 PM',
    '9:00 PM',
    'Fort Canning Park',
    'pictures/skate.jpeg',
    '2025-12-19T19:00:00+08:00',
    '2025-12-19T21:00:00+08:00'
);

INSERT INTO events VALUES (
    'Art Jamming & Chill',
    'arts',
    '2025-11-22',
    '2:00 PM',
    '5:00 PM',
    'Tanjong Hall',
    'pictures/art.jpg',
    '2025-11-22T14:00:00+08:00',
    '2025-11-22T17:00:00+08:00'
);

INSERT INTO events VALUES (
    'AI & Robotics Demo Day',
    'tech',
    '2025-12-06',
    '10:00 AM',
    '1:00 PM',
    'SMU Labs',
    'pictures/robotics.webp',
    '2025-12-06T10:00:00+08:00',
    '2025-12-06T13:00:00+08:00'
);

INSERT INTO events VALUES (
    'Eco-Smart Upcycling Workshop',
    'arts',
    '2025-12-13',
    '3:00 PM',
    '6:00 PM',
    'T3 Lobby',
    'pictures/upcycling.jpg',
    '2025-12-13T15:00:00+08:00',
    '2025-12-13T18:00:00+08:00'
);

INSERT INTO events VALUES (
    'Career Coffee Chats',
    'career',
    '2025-12-04',
    '4:00 PM',
    '6:00 PM',
    'LKCSB Atrium',
    'pictures/chat.webp',
    '2025-12-04T16:00:00+08:00',
    '2025-12-04T18:00:00+08:00'
);