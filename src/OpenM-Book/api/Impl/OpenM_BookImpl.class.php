<?php

Import::php("OpenM-Book.api.OpenM_Book");
Import::php("OpenM-Book.api.Impl.OpenM_Book_AdminImpl");
Import::php("OpenM-Book.api.OpenM_Book_Moderator");
Import::php("OpenM-Book.api.Impl.OpenM_BookCommonsImpl");

/**
 * 
 * @package OpenM 
 * @subpackage OpenM\OpenM-Book\api\Impl  
 * @author Nicolas Rouzeaud & Gaël SAUNIER
 */
class OpenM_BookImpl extends OpenM_BookCommonsImpl implements OpenM_Book {

    const MAX_USER_NUMBER_RESULT = 40;

    /**
     * OK
     */
    public function registerMeIntoCommunity($communityId) {
        if (!OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");

        if (!$this->isUserRegistered())
            return $this->error;

        $communitiyToSectionDAO = new OpenM_Book_Community_To_SectionDAO();
        $communityToSection = $communitiyToSectionDAO->getFromGroup($communityId);
        if ($communityToSection == null)
            return $this->error("communityId not found");

        OpenM_Log::debug("community found in DAO", __CLASS__, __METHOD__, __LINE__);
        OpenM_Log::debug("check if you're not banned from community", __CLASS__, __METHOD__, __LINE__);
        $communityBannedDAO = new OpenM_Book_Community_Banned_UsersDAO();
        if ($communityBannedDAO->isUserBanned($this->user->get(OpenM_Book_UserDAO::ID)->toInt(), $communityId))
            return $this->error("you're banned from this community");

        $communityUsersDAO = new OpenM_Book_Community_Content_UserDAO();
        $communityUser = $communityUsersDAO->get($communityId, $this->user->get(OpenM_Book_UserDAO::ID));
        if ($communityUser != null)
            return $this->error("user already in community");

        $communityUsersDAO->create($communityId, $this->user->get(OpenM_Book_UserDAO::ID)->toInt());
        return $this->ok();
    }

    /**
     * @todo finish dev & test
     */
    public function modifyMyVisibilityOnCommunity($communityId, $visibleByIdJSONList) {
        if (!OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");
        if (!String::isString($visibleByIdJSONList))
            return $this->error("visibleByCommunityIdJSONList must be a string");
        $visibleByCommunityId_array = OpenM_MapConvertor::JSONToArray($visibleByIdJSONList);
        if ($visibleByIdJSONList === false)
            return $this->error("visibleByCommunityIdJSONList is not in json format");
        foreach ($visibleByCommunityId_array as $value) {
            if (!is_numeric($value))
                return $this->error("visibleByCommunityIdJSONList must be an array of numerical values in JSON");
        }

        if (!$this->isUserRegistered())
            return $this->error;
        else
            $user = $this->user;

        $communitiyToSectionDAO = new OpenM_Book_Community_To_SectionDAO();
        $communityToSection = $communitiyToSectionDAO->getFromGroup($communityId);
        if ($communityToSection == null)
            return $this->error("communityId not found");

        $communityUsersDAO = new OpenM_Book_Community_Content_UserDAO();
        $communityUser = $communityUsersDAO->get($communityId, $user->get(OpenM_Book_UserDAO::ID));
        if ($communityUser == null)
            return $this->error("user not registered in this community");

        $communityVisibilityDAO = new OpenM_Book_Community_VisibilityDAO();
        $communityVisibility = $communityVisibilityDAO->get($user->get(OpenM_Book_UserDAO::ID), $communityId);
        $visibilityGroup = $communityVisibility->get(OpenM_Book_Community_VisibilityDAO::VISIBILITY_ID);

        if ($visibilityGroup == null) {
            $groupDAO = new OpenM_Book_GroupDAO();
            $group = $groupDAO->create("visibility");
            $visibilityGroup = $communityVisibilityDAO->create($user->get(OpenM_Book_UserDAO::ID), $communityId, $group->get(OpenM_Book_GroupDAO::ID));
        }

        $groupContentGroup = new OpenM_Book_Group_Content_GroupDAO();
        $groupsVisibility = $groupContentGroup->getChilds($visibilityGroup);
        $e = $groupsVisibility->keys();
        $communityVisibiliies_update = ArrayList::from($visibleByCommunityId_array);
        while ($e->hasNext()) {
            $key = $e->next();
            /**
             * @todo implement case of user or group
             */
            if ($communityVisibiliies_update->contains($key))
                $communityVisibiliies_update->remove($key);
            else
                $communityVisibilityDAO->delete($user->get(OpenM_Book_UserDAO::ID), $communityId, $key);
        }
        /**
         * @todo implement add of user or group
         * check if group / user exist
         */
        $e2 = $communityVisibiliies_update->enum();
        while ($e2->hasNext())
            $communityVisibilityDAO->create($user->get(OpenM_Book_UserDAO::ID), $communityId, $e2->next());

        return $this->ok();
    }

    /**
     * @todo provide my visibility retriction
     * dev & test
     */
    public function getCommunity($communityId = null) {
        if ($communityId != null && !OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");

        if (!$this->isUserRegistered())
            return $this->error;

        OpenM_Log::debug("load group from DAO", __CLASS__, __METHOD__, __LINE__);
        $groupDAO = new OpenM_Book_GroupDAO();
        if ($communityId == null)
            $group = $groupDAO->getCommunityRoot();
        else
            $group = $groupDAO->get($communityId);
        if ($group == null)
            return $this->error("group not found");
        $communityId = $group->get(OpenM_Book_GroupDAO::ID)->toInt();
        OpenM_Log::debug("group found in DAO", __CLASS__, __METHOD__, __LINE__);
        OpenM_Log::debug("Check if it's a community", __CLASS__, __METHOD__, __LINE__);
        $sectionDAO = new OpenM_Book_SectionDAO();
        $section = $sectionDAO->getFromCommunity($communityId);
        if ($section == null)
            return $this->error("It's not a community");

        OpenM_Log::debug("it's a community", __CLASS__, __METHOD__, __LINE__);
        $return = $this->ok()
                ->put(self::RETURN_COMMUNITY_ID_PARAMETER, $group->get(OpenM_Book_GroupDAO::ID)->toInt())
                ->put(self::RETURN_COMMUNITY_NAME_PARAMETER, $group->get(OpenM_Book_GroupDAO::NAME));

        $communityBannedDAO = new OpenM_Book_Community_Banned_UsersDAO();
        OpenM_Log::debug("check if user is banned of community parent", __CLASS__, __METHOD__, __LINE__);
        if ($communityBannedDAO->isUserBanned($this->user->get(OpenM_Book_UserDAO::ID)->toInt(), $communityId))
            return $return->put(self::RETURN_YOU_ARE_BANNED_PARAMETER, self::TRUE_PARAMETER_VALUE);

        $groupContentGroupDAO = new OpenM_Book_Group_Content_GroupDAO();
        OpenM_Log::debug("load community childs", __CLASS__, __METHOD__, __LINE__);
        $groups = $groupContentGroupDAO->getChilds($communityId);
        $communityList = new HashtableString();
        $e = $groups->keys();
        $i = 0;
        while ($e->hasNext()) {
            $g = $groups->get($e->next());
            $gr = new HashtableString();
            $gr->put(self::RETURN_COMMUNITY_ID_PARAMETER, $g->get(OpenM_Book_GroupDAO::ID)->toInt())
                    ->put(self::RETURN_COMMUNITY_NAME_PARAMETER, $g->get(OpenM_Book_GroupDAO::NAME));
            $communityList->put($i, $gr);
            $i++;
        }

        if ($groups->size() != 0)
            $return->put(self::RETURN_COMMUNITY_CANT_BE_REMOVED_PARAMETER, self::TRUE_PARAMETER_VALUE);

        OpenM_Log::debug("Check if can register in community", __CLASS__, __METHOD__, __LINE__);
        if ($section->get(OpenM_Book_SectionDAO::USER_CAN_REGISTER)->toInt() == OpenM_Book_SectionDAO::ACTIVATED)
            $return->put(self::RETURN_USER_CAN_REGISTER_PARAMETER, self::TRUE_PARAMETER_VALUE);

        OpenM_Log::debug("recover branch childs from DAO", __CLASS__, __METHOD__, __LINE__);
        $sectionChilds = $sectionDAO->getFromParent($section->get(OpenM_Book_SectionDAO::ID)->toInt());

        OpenM_Log::debug("Check if no branch child found and no community child found in DAO", __CLASS__, __METHOD__, __LINE__);
        if ($sectionChilds->size() == 1) {
            OpenM_Log::debug("Only one branch child found in DAO", __CLASS__, __METHOD__, __LINE__);
            $sectionChild = $sectionChilds->get($sectionChilds->keys()->next());
            if ($sectionChild->get(OpenM_Book_SectionDAO::USER_CAN_ADD_COMMUNITY)->toInt() == OpenM_Book_SectionDAO::ACTIVATED)
                $return->put(self::RETURN_USER_CAN_ADD_COMMUNITY_PARAMETER, self::TRUE_PARAMETER_VALUE);
            if ($sectionChild->get(OpenM_Book_SectionDAO::ONLY_ONE_COMMUNITY)->toInt() == OpenM_Book_SectionDAO::ACTIVATED)
                $return->put(self::RETURN_FORBIDDEN_TO_ADD_COMMUNITY_PARAMETER, self::TRUE_PARAMETER_VALUE);
        }
        else {
            $return->put(self::RETURN_FORBIDDEN_TO_ADD_COMMUNITY_PARAMETER, self::TRUE_PARAMETER_VALUE);
        }

        if ($sectionChilds->size() > 1)
            $return->put(self::RETURN_COMMUNITY_CANT_BE_REMOVED_PARAMETER, self::TRUE_PARAMETER_VALUE);

        $communityModeratorDAO = new OpenM_Book_Community_ModeratorDAO();
        OpenM_Log::debug("check if user is moderator of community parent", __CLASS__, __METHOD__, __LINE__);
        if ($communityModeratorDAO->isUserModerator($this->user->get(OpenM_Book_UserDAO::ID)->toInt(), $communityId))
            $return->put(self::RETURN_YOU_ARE_COMMUNITY_MODERATOR_PARAMETER, self::TRUE_PARAMETER_VALUE);

        $communityContentUserDAO = new OpenM_Book_Community_Content_UserDAO();
        OpenM_Log::debug("check if user is already registered in community", __CLASS__, __METHOD__, __LINE__);
        $communityUser = $communityContentUserDAO->get($communityId, $this->user->get(OpenM_Book_UserDAO::ID)->toInt());
        if ($communityUser != null)
            $return->put(self::RETURN_USER_ALREADY_REGISTERED_PARAMETER, self::TRUE_PARAMETER_VALUE);

        return $return->put(self::RETURN_COMMUNITY_CHILDS_PARAMETER, $communityList);
    }

    private function isIdValid($propertyId) {
        if (is_int($propertyId))
            return true;
        if (!String::isString($propertyId))
            return false;
        return RegExp::preg(OpenM_Groups::ID_PARAMETER_PATERN, "$propertyId");
    }

    /**
     * OK
     * 
     */
    public function addCommunity($name, $communityParentId) {
        if (!String::isString($name))
            return $this->error("name must be a string");
        if (!OpenM_Book_Tool::isGroupIdValid($communityParentId))
            return $this->error("communityParentId must be in a valid format");
        if (String::isString($communityParentId))
            $communityParentId = intval("$communityParentId");

        if ($this->isUserRegistered())
            $user = $this->user;
        else
            return $this->error;

        OpenM_Log::debug("search community parent in DAO", __CLASS__, __METHOD__, __LINE__);
        $communitiyToSectionDAO = new OpenM_Book_Community_To_SectionDAO();
        $communityToSection = $communitiyToSectionDAO->getFromGroup($communityParentId);
        if ($communityToSection == null)
            return $this->error("communityParentId not found");

        OpenM_Log::debug("community parent found in DAO", __CLASS__, __METHOD__, __LINE__);
        OpenM_Log::debug("check if you're not banned from community parent", __CLASS__, __METHOD__, __LINE__);
        $communityBannedDAO = new OpenM_Book_Community_Banned_UsersDAO();
        OpenM_Log::debug("check if user is banned of community parent", __CLASS__, __METHOD__, __LINE__);
        if ($communityBannedDAO->isUserBanned($this->user->get(OpenM_Book_UserDAO::ID)->toInt(), $communityParentId))
            return $this->error("you're banned from this community");

        OpenM_Log::debug("search branch parent in DAO", __CLASS__, __METHOD__, __LINE__);
        $sectionDAO = new OpenM_Book_SectionDAO();
        $sectionParent = $sectionDAO->get($communityToSection->get(OpenM_Book_Community_To_SectionDAO::SECTION_ID)->toInt());
        if ($sectionParent == null)
            return $this->error("branch parent not found");

        OpenM_Log::debug("search branch childs in DAO", __CLASS__, __METHOD__, __LINE__);
        $sectionChilds = $sectionDAO->getFromParent($sectionParent->get(OpenM_Book_SectionDAO::ID)->toInt());
        if ($sectionChilds->size() != 1)
            return $this->error("branch parent must have exactly one branch child");

        $sectionChild = $sectionChilds->get($sectionChilds->keys()->next());

        OpenM_Log::debug("branch parent found in DAO (" . $sectionChild->get(OpenM_Book_SectionDAO::ID) . ")", __CLASS__, __METHOD__, __LINE__);
        OpenM_Log::debug("check if branch accept to contain more than one community", __CLASS__, __METHOD__, __LINE__);
        if ($sectionChild->get(OpenM_Book_SectionDAO::ONLY_ONE_COMMUNITY)->toInt() == OpenM_Book_SectionDAO::ACTIVATED)
            return $this->error("this branch could contain only one community");

        OpenM_Log::debug("check if branch permit user to add community", __CLASS__, __METHOD__, __LINE__);
        if ($sectionChild->get(OpenM_Book_SectionDAO::USER_CAN_ADD_COMMUNITY)->toInt() == OpenM_Book_SectionDAO::DESACTIVATED) {
            $communityModeratorDAO = new OpenM_Book_Community_ModeratorDAO();
            OpenM_Log::debug("check if you're community moderator of parent community", __CLASS__, __METHOD__, __LINE__);
            if (!$communityModeratorDAO->isUserModerator($user->get(OpenM_Book_UserDAO::ID), $communityParentId)) {
                $adminDAO = new OpenM_Book_AdminDAO();
                $admin = $adminDAO->get($user->get(OpenM_Book_UserDAO::UID));
                OpenM_Log::debug("check if you're an admin", __CLASS__, __METHOD__, __LINE__);
                if ($admin == null)
                    return $this->error(OpenM_Book_Moderator::RETURN_ERROR_MESSAGE_NOT_ENOUGH_RIGHTS_VALUE);
            }
        }

        OpenM_Log::debug("check if name respect REG EXP associated to section", __CLASS__, __METHOD__, __LINE__);
        if (!RegExp::preg("/^" . $sectionChild->get(OpenM_Book_SectionDAO::REG_EXP) . "$/", $name))
            return $this->error("name isn't in a correct format (" . $sectionChild->get(OpenM_Book_SectionDAO::REG_EXP) . ")");

        $community = OpenM_Book_AdminImpl::_addCommunity($communityParentId, $name, $sectionChild);
        return $this->ok()->put(self::RETURN_COMMUNITY_ID_PARAMETER, $community->get(OpenM_Book_GroupDAO::ID));
    }

    public function getCommunityAncestors($communityId) {
        if (!OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");

        if (!$this->isUserRegistered())
            return $this->error;

        OpenM_Log::debug("search ancestors in DAO", __CLASS__, __METHOD__, __LINE__);
        $communityToSectionDAO = new OpenM_Book_Community_To_SectionDAO();
        $ancestors = $communityToSectionDAO->getCommunityAncestors($communityId);
        $return = $this->ok();
        $e = $ancestors->keys();
        while ($e->hasNext()) {
            $key = $e->next();
            $ancestor = $ancestors->get($key);
            $a = new HashtableString();
            $return->put($key, $a->put(self::RETURN_COMMUNITY_ID_PARAMETER, $ancestor->get(OpenM_Book_Group_Content_GroupDAO::GROUP_ID))
                            ->put(self::RETURN_COMMUNITY_PARENT_PARAMETER, $ancestor->get(OpenM_Book_Group_Content_GroupDAO::GROUP_PARENT_ID))
                            ->put(self::RETURN_COMMUNITY_NAME_PARAMETER, $ancestor->get(OpenM_Book_GroupDAO::NAME), $a));
        }
        return $return;
    }

    public function getCommunityParent($communityId) {
        if (!OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");

        if (!$this->isUserRegistered())
            return $this->error;

        return $this->notImplemented();
    }

    /**
     * 
     * Visibility restriction not take in account !
     */
    public function getCommunityUsers($communityId, $start = null, $numberOfResult = null) {
        if (!OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");
        if ($start == null)
            $start = 0;
        if (!String::isString($start) && !Float::isNumber($start))
            return $this->error("start must be a number");
        if (String::isString($start))
            $start = intval("$start");
        if ($numberOfResult == null)
            $numberOfResult = self::MAX_USER_NUMBER_RESULT;
        if (!String::isString($numberOfResult) && !Float::isNumber($numberOfResult))
            return $this->error("numberOfResult must be a number");
        if (String::isString($numberOfResult))
            $numberOfResult = intval("$numberOfResult");

        if (!$this->isUserRegistered())
            return $this->error;

        OpenM_Log::debug("search users valid in DAO", __CLASS__, __METHOD__, __LINE__);
        $communityContentUserDAO = new OpenM_Book_Community_Content_UserDAO();
        $users = $communityContentUserDAO->getUsers($communityId, $start, $numberOfResult);
        $userList = new HashtableString();
        $e = $users->keys();
        $i = $start;
        while ($e->hasNext()) {
            $user = $users->get($e->next());
            $u = new HashtableString();
            $u->put(self::RETURN_USER_ID_PARAMETER, $user->get(OpenM_Book_UserDAO::ID))
                    ->put(self::RETURN_USER_NAME_PARAMETER, $user->get(OpenM_Book_UserDAO::FIRST_NAME) . " " . $user->get(OpenM_Book_UserDAO::LAST_NAME));
            $userList->put($i, $u);
            $i++;
        }
        OpenM_Log::debug("count all users valid in DAO", __CLASS__, __METHOD__, __LINE__);
        $count = $communityContentUserDAO->countOfUsers($communityId);
        return $this->ok()->put(self::RETURN_USER_LIST_COUNT_PARAMETER, $count)
                        ->put(self::RETURN_USER_LIST_PARAMETER, $userList);
    }

    /**
     * 
     * Visibility restriction not take in account !
     */
    public function getCommunityNotValidUsers($communityId, $start = null, $numberOfResult = null) {
        if (!OpenM_Book_Tool::isGroupIdValid($communityId))
            return $this->error("communityId must be in a valid format");
        if (String::isString($communityId))
            $communityId = intval("$communityId");
        if ($start == null)
            $start = 0;
        if (!String::isString($start) && !Float::isNumber($start))
            return $this->error("start must be a number");
        if (String::isString($start))
            $start = intval("$start");
        if ($numberOfResult == null)
            $numberOfResult = self::MAX_USER_NUMBER_RESULT;
        if (!String::isString($numberOfResult) && !Float::isNumber($numberOfResult))
            return $this->error("numberOfResult must be a number");
        if (String::isString($numberOfResult))
            $numberOfResult = intval("$numberOfResult");

        if (!$this->isUserRegistered())
            return $this->error;

        OpenM_Log::debug("search users valid in DAO", __CLASS__, __METHOD__, __LINE__);
        $communityContentUserDAO = new OpenM_Book_Community_Content_UserDAO();
        $users = $communityContentUserDAO->getUsers($communityId, $start, $numberOfResult, false);
        $userList = new HashtableString();
        $e = $users->keys();
        $i = $start;
        while ($e->hasNext()) {
            $user = $users->get($e->next());
            $u = new HashtableString();
            $u->put(self::RETURN_USER_ID_PARAMETER, $user->get(OpenM_Book_UserDAO::ID)->toInt())
                    ->put(self::RETURN_USER_NAME_PARAMETER, $user->get(OpenM_Book_UserDAO::FIRST_NAME) . " " . $user->get(OpenM_Book_UserDAO::LAST_NAME))
                    ->put(self::RETURN_COMMUNITY_ID_PARAMETER, $user->get(OpenM_Book_Community_Content_UserDAO::COMMUNITY_ID)->toInt())
                    ->put(self::RETURN_COMMUNITY_NAME_PARAMETER, $user->get(OpenM_Book_GroupDAO::NAME));
            $userList->put($i, $u);
            $i++;
        }
        OpenM_Log::debug("count all users valid in DAO", __CLASS__, __METHOD__, __LINE__);
        $count = $communityContentUserDAO->countOfUsers($communityId, false);
        return $this->ok()->put(self::RETURN_USER_LIST_COUNT_PARAMETER, $count)
                        ->put(self::RETURN_USER_LIST_PARAMETER, $userList);
    }

    public function invitPeople($mailJSONList) {
        return $this->notImplemented();
    }

    public function removeMeFromCommunity($communistyId) {
        return $this->notImplemented();
    }

    public function signal($url, $message, $type = self::SIGNAL_TYPE_BUG, $id = null) {
        return $this->notImplemented();
    }

    public function validateUser($userId, $communityId) {
        return $this->notImplemented();
    }

}

?>