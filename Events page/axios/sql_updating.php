<?php error_reporting(0); ?>  
<?php
spl_autoload_register(
    function ($class) {
        require_once "../model/$class.php";
    }
);
?>

<?php
    $dao = new EventCollectionDAO();

    $personID = $_GET["personID"];
    $eventID = $_GET["eventID"];
    $option = $_GET["option"];

    if ($option == "add") {
        $dao->userAddEvent($personID, $eventID);
        echo "Event $eventID added";
    }

    if ($option == "remove") {
        $dao->userRemoveEvent($personID, $eventID);
        echo "Event $eventID removed";
    }

    if ($option == "removeAll") {
        $dao->removeAllEvents($personID);
        echo "All events removed";
    }
?>