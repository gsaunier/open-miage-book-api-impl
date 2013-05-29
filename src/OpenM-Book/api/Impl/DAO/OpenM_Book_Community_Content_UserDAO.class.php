<?php

Import::php("OpenM-Book.api.Impl.DAO.OpenM_Book_DAO");

/**
 * Description of OpenM_Book_Group_Content_UserDAO
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-Book\api\Impl\DAO  
 * @author Gael SAUNIER
 */
class OpenM_Book_Community_Content_UserDAO extends OpenM_Book_DAO {

    const OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME = "OpenM_BOOK_COMMUNITY_CONTENT_USER";
    const COMMUNITY_ID = "group_id";
    const USER_ID = "user_id";
    const IS_VALIDATED = "isValidated";
    const CREATION_TIME = "creation_time";
    const VALIDATION_TIME = "validation_time";
    const VALIDATED = 1;
    const NOT_VALIDATED = 0;

    public function create($groupId, $userId, $isValid = false) {
        $time = time();

        $array = array(
            self::COMMUNITY_ID => intval($groupId),
            self::USER_ID => intval($userId),
            self::IS_VALIDATED => (($isValid) ? 1 : 0),
            self::CREATION_TIME => $time
        );

        if ($isValid)
            $array[self::VALIDATION_TIME] = $time;

        self::$db->request(OpenM_DB::insert($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), $array));

        $return = new HashtableString();
        return $return->put(self::COMMUNITY_ID, $groupId)
                        ->put(self::USER_ID, $userId)
                        ->put(self::CREATION_TIME, $time)
                        ->put(self::IS_VALIDATED, $isValid)
                        ->put(self::VALIDATION_TIME, ($isValid) ? $time : null);
    }

    public function delete($groupId, $userId) {
        self::$db->request(OpenM_DB::delete($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), array(
                    self::USER_ID => intval($userId),
                    self::COMMUNITY_ID => intval($groupId)
                )));
    }

    public function deleteFromGroup($groupId) {
        self::$db->request(OpenM_DB::delete($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), array(
                    self::COMMUNITY_ID => intval($groupId)
                )));
    }

    public function deleteFromUser($userId) {
        self::$db->request(OpenM_DB::delete($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), array(
                    self::USER_ID => intval($userId)
                )));
    }

    public function get($groupId, $userId) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::select($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), array(
                            self::USER_ID => intval($userId),
                            self::COMMUNITY_ID => intval($groupId)
                        )));
    }

    public function countOfUsers($communityId, $valid = true) {
        $communities = OpenM_DB::select($this->getTABLE(OpenM_Book_Group_Content_GroupDAO::OPENM_BOOK_GROUP_CONTENT_GROUP_INDEX_TABLE_NAME), array(
                    OpenM_Book_Group_Content_GroupDAO::GROUP_PARENT_ID => intval($communityId)
                        ), array(
                    OpenM_Book_Group_Content_GroupDAO::GROUP_ID
                ));
        $count = self::$db->request_fetch_array("SELECT count(*) as count FROM "
                . $this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME)
                . " WHERE (" . self::COMMUNITY_ID . " IN ($communities) OR " . self::COMMUNITY_ID . "=$communityId)"
                . " AND " . self::IS_VALIDATED . "=" . (($valid) ? (self::VALIDATED) : (self::NOT_VALIDATED))
        );
        return intval($count["count"]);
    }

    public function getFromCommunity($groupId) {
        return self::$db->request_HashtableString(OpenM_DB::select($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), array(
                            self::COMMUNITY_ID => intval($groupId)
                        )), self::USER_ID);
    }

    public function getFromUser($userId) {
        return self::$db->request_HashtableString(OpenM_DB::select($this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME), array(
                            self::USER_ID => intval($userId)
                        )), self::COMMUNITY_ID);
    }

    public function getUsers($communityId, $start, $maxNbResult, $valid = true) {
        $communityId = intval($communityId);
        $communities = OpenM_DB::select($this->getTABLE(OpenM_Book_Group_Content_GroupDAO::OPENM_BOOK_GROUP_CONTENT_GROUP_INDEX_TABLE_NAME), array(
                    OpenM_Book_Group_Content_GroupDAO::GROUP_PARENT_ID => $communityId
                        ), array(
                    OpenM_Book_Group_Content_GroupDAO::GROUP_ID
                ));
        $usersIds = "SELECT cc.* " . (!$valid ? ", g." . OpenM_Book_GroupDAO::NAME : "") . " FROM "
                . $this->getTABLE(self::OPENM_BOOK_COMMUNITY_CONTENT_USER_TABLE_NAME) . " cc"
                . ((!$valid) ? (", " . $this->getTABLE(OpenM_Book_GroupDAO::OpenM_BOOK_GROUP_TABLE_NAME) . " g ") : "")
                . " WHERE (cc." . self::COMMUNITY_ID . " IN ($communities) OR cc." . self::COMMUNITY_ID . "=$communityId)"
                . " AND cc." . self::IS_VALIDATED . "=" . (($valid) ? (self::VALIDATED) : (self::NOT_VALIDATED))
                . ((!$valid) ? (" AND cc." . self::COMMUNITY_ID . "=g." . OpenM_Book_GroupDAO::ID) : "");
        $users = "SELECT u." . OpenM_Book_UserDAO::ID . ", u."
                . OpenM_Book_UserDAO::FIRST_NAME . ", u." . OpenM_Book_UserDAO::LAST_NAME
                . ", c." . self::COMMUNITY_ID . ((!$valid) ? (", c." . OpenM_Book_GroupDAO::NAME ) : "")
                . " FROM " . $this->getTABLE(OpenM_Book_UserDAO::OpenM_Book_User_Table_Name) . " u, "
                . " ($usersIds) c"
                . " WHERE u." . OpenM_Book_UserDAO::ID . "=c." . self::USER_ID
                . ($valid ? (" GROUP BY " . OpenM_Book_UserDAO::ID) : "")
                . " ORDER BY u." . OpenM_Book_UserDAO::FIRST_NAME . ", u." . OpenM_Book_UserDAO::LAST_NAME;
        return self::$db->request_HashtableString(self::$db->limit($users, $maxNbResult, $start)
                        , self::COMMUNITY_ID);
    }

}

?>