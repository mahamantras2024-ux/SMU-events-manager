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
}
