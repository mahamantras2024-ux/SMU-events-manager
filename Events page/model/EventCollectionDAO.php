<?php

class EventCollectionDAO
{
    public function getEvents()
    {
        $sql = "select * from events";

        $connMgr = new ConnectionManager();
        $conn = $connMgr->getConnection();

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = new Event(
                $row["id"],
                $row["title"],
                $row["category"],
                $row["date"],
                $row["start_time"],
                $row["end_time"],
                $row["location"],
                $row["picture"],
                $row["startISO"],
                $row["endISO"]
            );
        }

        $stmt = null;
        $conn = null;
        return $events;
    }

    public function getFilteredEvents($filter) {
        $sql = "select * from events where category = :filter";

        $connMgr = new ConnectionManager();
        $conn = $connMgr->getConnection();

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':filter', $filter, PDO::PARAM_STR);

        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = new Event(
                $row["id"],
                $row["title"],
                $row["category"],
                $row["date"],
                $row["start_time"],
                $row["end_time"],
                $row["location"],
                $row["picture"],
                $row["startISO"],
                $row["endISO"]
            );
        }

        $stmt = null;
        $conn = null;
        return $events;

    }

    // add event to user
    // public function userAddEvent()

    public function getUserId($username) {
        $connMgr = new ConnectionManager();
        $conn = $connMgr->getConnection();

        $sql = 'SELECT id from users WHERE username = :username';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);

        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        while ($row = $stmt->fetch()) {
            $userID = $row["id"];
        }

        $stmt = null;
        $conn = null;
        return $userID;
    }

    // for loading a user's saved events
    public function getUsersEvents($userID) {
        $connMgr = new ConnectionManager();
        $conn = $connMgr->getConnection();

        $sql = 'SELECT id, title, category, date, start_time, end_time, location, picture, startISO, endISO FROM events e INNER JOIN event_person ep
                ON e.id = ep.event_id WHERE person_id = :userID';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);

        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = new Event(
                $row["id"],
                $row["title"],
                $row["category"],
                $row["date"],
                $row["start_time"],
                $row["end_time"],
                $row["location"],
                $row["picture"],
                $row["startISO"],
                $row["endISO"]
            );
        }

        $stmt = null;
        $conn = null;

        return $events;
    }
}
