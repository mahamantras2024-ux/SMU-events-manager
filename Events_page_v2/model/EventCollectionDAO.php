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
                $row["endISO"],
                $row["details"]
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
                $row["endISO"],
                $row["details"]
            );
        }

        $stmt = null;
        $conn = null;
        return $events;

    }

    // add event to user
    public function userAddEvent($personID, $eventID) {
        $conn = (new ConnectionManager())->getConnection();

        $sql = 'INSERT INTO event_person (person_id, event_id, role)
                VALUES (:personID, :eventID, :role)';
        $stmt = $conn->prepare($sql);
        $role = 'participant';
        $stmt->bindParam(':personID', $personID, PDO::PARAM_INT);
        $stmt->bindParam(':eventID',  $eventID,  PDO::PARAM_INT);
        $stmt->bindParam(':role',     $role,     PDO::PARAM_STR);
        $stmt->execute();

        $stmt = null; $conn = null;
    }

    // remove event from user
    public function userRemoveEvent($personID, $eventID) {
        $connMgr = new ConnectionManager();
		$conn = $connMgr->getConnection();

		$sql = 'DELETE FROM event_person
                WHERE person_id = :personID and event_id = :eventID';
		$stmt = $conn->prepare($sql);
		$stmt->bindParam(':personID', $personID, PDO::PARAM_INT);
		$stmt->bindParam(':eventID', $eventID, PDO::PARAM_INT);

		$stmt->execute();
		$stmt = null;
		$conn = null;
    }

    // remove all events
    public function removeAllEvents($personID) {
        $connMgr = new ConnectionManager();
		$conn = $connMgr->getConnection();

        $sql = 'DELETE FROM event_person WHERE person_id = :personID';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':personID', $personID, PDO::PARAM_INT);

        $stmt->execute();
        $stmt = null;
        $conn = null;
    }

    // get user ID from their login username
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
        $conn = (new ConnectionManager())->getConnection();

        $sql = 'SELECT DISTINCT e.id, e.title, e.category, e.date, e.start_time, e.end_time,
                    e.location, e.picture, e.startISO, e.endISO, e.details
                FROM events e
                JOIN event_person ep ON ep.event_id = e.id
                WHERE ep.person_id = :userID
                AND (ep.role IS NULL OR ep.role = "participant")
                ORDER BY e.date DESC, e.start_time DESC';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = new Event(
                $row["id"], $row["title"], $row["category"], $row["date"],
                $row["start_time"], $row["end_time"], $row["location"],
                $row["picture"], $row["startISO"], $row["endISO"], $row["details"]
            );
        }
        $stmt = null; $conn = null;
        return $events;
    }
}
