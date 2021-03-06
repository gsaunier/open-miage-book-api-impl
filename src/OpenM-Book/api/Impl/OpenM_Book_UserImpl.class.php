<?php

Import::php("OpenM-Book.api.OpenM_Book_User");
Import::php("OpenM-Book.api.Impl.OpenM_Book_AdminImpl");
Import::php("OpenM-Book.api.OpenM_Book_Moderator");
Import::php("OpenM-Book.api.Impl.OpenM_BookCommonsImpl");
Import::php("OpenM-Mail.api.OpenM_MailTool");
Import::php("util.JSON.OpenM_MapConvertor");

/**
 * 
 * @package OpenM 
 * @subpackage OpenM\OpenM-Book\api\Impl
 * @license http://www.apache.org/licenses/LICENSE-2.0 Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * @link http://www.open-miage.org
 * @author Nicolas Rouzeaud & Gaël SAUNIER
 */
class OpenM_Book_UserImpl extends OpenM_BookCommonsImpl implements OpenM_Book_User {

    /**
     * OK
     */
    public function addPropertyValue($propertyId, $propertyValue) {
        if (!$this->isIdValid($propertyId))
            return $this->error("PropertyId must be an integer");
        if (!String::isString($propertyValue))
            return $this->error("PropertyValue must be a string");

        if ($this->isUserRegistered())
            $user = $this->user;
        else
            return $this->error;

        $propertyDAO = new OpenM_Book_User_PropertyDAO();
        OpenM_Log::debug("check if propertyId exist in DAO", __CLASS__, __METHOD__, __LINE__);
        $property = $propertyDAO->getById($propertyId);
        if ($property == null)
            return $this->error("propertyId not found");

        $error = $this->_checkValue($property->get(OpenM_Book_User_PropertyDAO::ID), $propertyValue);
        if ($error !== null)
            return $error;

        OpenM_Log::debug("propertyId exist in DAO", __CLASS__, __METHOD__, __LINE__);
        $groupDAO = new OpenM_Book_GroupDAO();
        OpenM_Log::debug("create property visibility group", __CLASS__, __METHOD__, __LINE__);
        $group = $groupDAO->create("visibility");
        $userPropertyValueDAO = new OpenM_Book_User_Property_ValueDAO();
        OpenM_Log::debug("create property value in DAO", __CLASS__, __METHOD__, __LINE__);
        $value = $userPropertyValueDAO->create($propertyId, $propertyValue, $user->get(OpenM_Book_UserDAO::ID), $group->get(OpenM_Book_GroupDAO::ID));

        $userDAO = new OpenM_Book_UserDAO();
        OpenM_Log::debug("update user update time in DAO", __CLASS__, __METHOD__, __LINE__);
        $userDAO->updateTime($user->get(OpenM_Book_UserDAO::ID));

        return $this->ok()
                        ->put(self::RETURN_USER_PROPERTY_VALUE_ID_PARAMETER, $value->get(OpenM_Book_User_Property_ValueDAO::ID))
                        ->put(self::RETURN_USER_PROPERTY_VALUE_VISIBILITY_PARAMETER, $value->get(OpenM_Book_User_Property_ValueDAO::VISIBILITY));
    }

    /**
     * OK
     */
    public function setPropertyValue($propertyValueId, $propertyValue) {
        if (!RegExp::preg("/^-?[0-9]+$/", $propertyValueId))
            return $this->error("propertyValueId must be an int");
        if (!String::isString($propertyValue))
            return $this->error("propertyValue must be a string");

        if ($this->isUserRegistered())
            $user = $this->user;
        else
            return $this->error;

        $userDAO = new OpenM_Book_UserDAO();
        $userId = $user->get(OpenM_Book_UserDAO::ID)->toInt();

        switch ($propertyValueId) {
            case self::FIRST_NAME_PROPERTY_VALUE_ID :
                if (!RegExp::preg("/^[a-zA-Z]([a-zA-Z]|[ \t])+[a-zA-Z]?$/", OpenM_Book_Tool::strlwr($propertyValue)))
                    return $this->error("firstName in bad format");
                $userDAO->update($userId, OpenM_Book_UserDAO::FIRST_NAME, $propertyValue);
                break;
            case self::LAST_NAME_PROPERTY_VALUE_ID :
                if (!RegExp::preg("/^[a-zA-Z]([a-zA-Z]|[ \t])+[a-zA-Z]?$/", OpenM_Book_Tool::strlwr($propertyValue)))
                    return $this->error("lastName in bad format");
                $userDAO->update($userId, OpenM_Book_UserDAO::LAST_NAME, $propertyValue);
                break;
            case self::PHOTO_ID_PROPERTY_VALUE_ID :
                if (!$this->isIdValid($propertyValue))
                    return $this->error("Photo ID not valid");
                $userDAO->update($userId, OpenM_Book_UserDAO::PHOTO, $propertyValue);
                break;
            case self::DEFAULT_EMAIL_PROPERTY_VALUE_ID :
                if (!OpenM_MailTool::isEMailValid($propertyValue))
                    return $this->error("mail not valid");
                $userDAO->update($userId, OpenM_Book_UserDAO::MAIL, $propertyValue);
                break;
            case self::BIRTHDAY_ID_PROPERTY_VALUE_ID :
                $date = new Date("$propertyValue");
                if ($date->plus(Delay::years(self::AGE_LIMIT_TO_REGISTER))->compareTo(Date::now()) < 0)
                    return $this->error("you must be older than " . self::AGE_LIMIT_TO_REGISTER . " years old");
                $userDAO->update($userId, OpenM_Book_User::BIRTHDAY_ID_PROPERTY_VALUE_ID, $propertyValue);
                break;
            default:
                OpenM_Log::debug("default property treatment", __CLASS__, __METHOD__, __LINE__);
                $propertyValueDAO = new OpenM_Book_User_Property_ValueDAO();
                OpenM_Log::debug("search property value in DAO", __CLASS__, __METHOD__, __LINE__);
                $userPropertyValue = $propertyValueDAO->get($propertyValueId);
                if ($userPropertyValue->size() == 0)
                    return $this->error(self::RETURN_ERROR_MESSAGE_PROPERTY_NOTFOUND_VALUE);
                OpenM_Log::debug("search property in DAO", __CLASS__, __METHOD__, __LINE__);
                $error = $this->_checkValue($userPropertyValue->get(OpenM_Book_User_Property_ValueDAO::PROPERTY_ID), $propertyValue);
                if ($error !== null)
                    return $error;
                OpenM_Log::debug("check if property is property of user", __CLASS__, __METHOD__, __LINE__);
                if ($userPropertyValue->get(OpenM_Book_User_Property_ValueDAO::USER_ID) != $this->user->get(OpenM_Book_UserDAO::ID))
                    return $this->error("it's not your property");
                OpenM_Log::debug("property value found in DAO", __CLASS__, __METHOD__, __LINE__);
                $propertyValueDAO->update($propertyValueId, $propertyValue);
                OpenM_Log::debug("property updated in DAO", __CLASS__, __METHOD__, __LINE__);
                break;
        }
        return $this->ok();
    }

    private function _checkValue($propertyId, $propertyValue) {
        $propertyDAO = new OpenM_Book_User_PropertyDAO();
        $property = $propertyDAO->getById($propertyId);
        OpenM_Log::debug("check if property value respect property reg_exp", __CLASS__, __METHOD__, __LINE__);
        if ($property === null)
            return $this->error("property not found");
        if ($property->get(OpenM_Book_User_PropertyDAO::REGEXP) . "" !== "" &&
                !RegExp::ereg("^" . $property->get(OpenM_Book_User_PropertyDAO::REGEXP) . "$", $propertyValue))
            return $this->error("property not respect reg_exp : ^" . $property->get(OpenM_Book_User_PropertyDAO::REGEXP) . "$");
    }

    /**
     * @todo in progress
     */
    public function setPropertyVisibility($propertyValueId, $visibilityGroupJSONList) {
        if (!RegExp::preg("/^-?[0-9]+$/", $propertyValueId))
            return $this->error("propertyValueId must be an int");
        $propertyValueId = intval("$propertyValueId");
        $array = OpenM_MapConvertor::JSONToArray($visibilityGroupJSONList);
        if ($array === null)
            return $this->error("visibilityGroupJSONList is malformed");
        foreach ($array as $value) {
            if (!is_numeric($value))
                return $this->error("visibilityGroupJSONList is malformed");
        }
        $visibilities = ArrayList::from($array);

        if ($this->isUserRegistered())
            $user = $this->user;
        else
            return $this->error;

        OpenM_Log::debug("check if property value is birthady", __CLASS__, __METHOD__, __LINE__);
        if ($propertyValueId == self::BIRTHDAY_ID_PROPERTY_VALUE_ID)
            $visibilityGroup = $this->user->get(OpenM_Book_UserDAO::BIRTHDAY_VISIBILITY)->toInt();
        else {
            OpenM_Log::debug("check if property value is user's property", __CLASS__, __METHOD__, __LINE__);
            $userPropertyValueDAO = new OpenM_Book_User_Property_ValueDAO();
            $value = $userPropertyValueDAO->get($propertyValueId);
            if ($value->get(OpenM_Book_User_Property_ValueDAO::USER_ID) != $this->user->get(OpenM_Book_UserDAO::ID))
                return $this->error("not your property");
            $visibilityGroup = $value->get(OpenM_Book_User_Property_ValueDAO::VISIBILITY);
        }

        $groupToAdd = new ArrayList();
        $groupToDelete = new ArrayList();

        $groupContentGroupDAO = new OpenM_Book_Group_Content_GroupDAO();
        $childs = $groupContentGroupDAO->getChilds($visibilityGroup);

        OpenM_Log::debug("found group to delete", __CLASS__, __METHOD__, __LINE__);
        $e = $childs->keys();
        while ($e->hasNext()) {
            $g = $e->next();
            if (!$visibilities->contains($g))
                $groupToDelete->add($g);
        }
        OpenM_Log::debug("found group to add", __CLASS__, __METHOD__, __LINE__);
        $j = $visibilities->enum();
        while ($j->hasNext()) {
            $g = $j->next();
            if (!$childs->containsKey($g))
                $groupToAdd->add($g);
        }

        OpenM_Log::debug("remove group", __CLASS__, __METHOD__, __LINE__);
        $k = $groupToDelete->enum();
        while ($k->hasNext())
            $groupContentGroupDAO->delete($visibilityGroup, $k->next());

        /**
         * /!\ no verification on groupId /!\
         */
        OpenM_Log::debug("add group", __CLASS__, __METHOD__, __LINE__);
        $l = $groupToAdd->enum();
        while ($l->hasNext())
            $groupContentGroupDAO->create($visibilityGroup, $l->next(), false);

        return $this->ok();
    }

    /**
     * OK
     */
    public function removePropertyValue($propertyValueId) {
        if (!$this->isIdValid($propertyValueId))
            return $this->error("propertyValueId must be a int");

        if ($this->isUserRegistered())
            $user = $this->user;
        else
            return $this->error;

        $userId = $user->get(OpenM_Book_UserDAO::ID)->toInt();
        $propertyValueDAO = new OpenM_Book_User_Property_ValueDAO();
        $propertyValue = $propertyValueDAO->get($propertyValueId);
        if ($propertyValue->size() == 0)
            return $this->error(self::RETURN_ERROR_MESSAGE_PROPERTY_NOTFOUND_VALUE);
        OpenM_Log::debug("check if it's property of user", __CLASS__, __METHOD__, __LINE__);
        if ($propertyValue->get(OpenM_Book_User_Property_ValueDAO::USER_ID) != $this->user->get(OpenM_Book_UserDAO::ID))
            return $this->error("it's not your property");

        OpenM_Log::debug("property owned by user", __CLASS__, __METHOD__, __LINE__);
        $propertyValueDAO->delete($propertyValueId);
        OpenM_Log::debug("delete visibility group", __CLASS__, __METHOD__, __LINE__);
        $groupDAO = new OpenM_Book_GroupDAO();
        $groupDAO->delete($propertyValue->get(OpenM_Book_User_Property_ValueDAO::VISIBILITY));
        OpenM_Log::debug("property deleted", __CLASS__, __METHOD__, __LINE__);
        $userDAO = new OpenM_Book_UserDAO();
        $userDAO->updateTime($userId);
        return $this->ok();
    }

    public function buildMyData() {
        $this->notImplemented();
    }

    public function unRegisterMe() {
        $this->notImplemented();
    }

    /**
     * OK
     */
    public function getUserProperties($userId = null, $basicOnly = null) {
        if (!String::isStringOrNull($userId))
            return $this->error("userId must be a string");
        if ($userId != null && !OpenM_Book_Tool::isUserIdValid($userId))
            return $this->error("userId must be in a valid format");
        if (!String::isStringOrNull($basicOnly) && !is_bool($basicOnly))
            return $this->error("basicOnly must be a string or a boolean");
        if ($basicOnly == null || (is_bool($basicOnly) && $basicOnly) || $basicOnly == self::TRUE_PARAMETER_VALUE)
            $basicOnly = self::TRUE_PARAMETER_VALUE;
        else if ($basicOnly != self::TRUE_PARAMETER_VALUE)
            $basicOnly = self::FALSE_PARAMETER_VALUE;

        if ($this->isUserRegistered())
            $user = $this->user;
        else
            return $this->error;

        $userIdCalling = $user->get(OpenM_Book_UserDAO::ID)->toInt();

        if ($userId == null) {
            OpenM_Log::debug("user calling is the targeted user", __CLASS__, __METHOD__, __LINE__);
            $userId = $userIdCalling;
        } else if ($userId == $userIdCalling) {
            OpenM_Log::debug("the targeted user is the user that calling method", __CLASS__, __METHOD__, __LINE__);
        } else {
            OpenM_Log::debug("search the targeted user in DAO", __CLASS__, __METHOD__, __LINE__);
            $userDAO = new OpenM_Book_UserDAO();
            $user = $userDAO->get($userId);
            if ($user == null)
                return $this->error(self::RETURN_ERROR_MESSAGE_USER_NOT_FOUND_VALUE);
            OpenM_Log::debug("the targeted user is found in DAO", __CLASS__, __METHOD__, __LINE__);
            $userId = $user->get(OpenM_Book_UserDAO::ID)->toInt();
        }

        $return = $this->ok();
        $isUserCalling = ($userId == $userIdCalling);

        if ($isUserCalling) {
            $adminDAO = new OpenM_Book_AdminDAO();
            OpenM_Log::debug("Check if user is admin", __CLASS__, __METHOD__, __LINE__);
            $admin = $adminDAO->get($this->user->get(OpenM_Book_UserDAO::UID));
            if ($admin != null) {
                OpenM_Log::debug("user is admin", __CLASS__, __METHOD__, __LINE__);
                $return->put(self::RETURN_USER_IS_ADMIN_PARAMETER, self::TRUE_PARAMETER_VALUE);
            }
        }

        $propertyList = new HashtableString();
        if ($basicOnly === self::FALSE_PARAMETER_VALUE) {

            $date = new Date($user->get(OpenM_Book_UserDAO::BIRTHDAY)->toInt());
            $birthdayDisplayed = false;
            if ($isUserCalling)
                $return->put(self::RETURN_USER_BIRTHDAY_PARAMETER, $date->toString("d/m/Y"));
            else {
                OpenM_Log::debug("recover group allowed to see birthday", __CLASS__, __METHOD__, __LINE__);
                $groupContentGroupDAO = new OpenM_Book_Group_Content_GroupDAO();
                $childs = $groupContentGroupDAO->getChilds($user->get(OpenM_Book_UserDAO::BIRTHDAY_VISIBILITY));
                $visibilities = new ArrayList();
                $e = $childs->keys();
                while ($e->hasNext())
                    $visibilities->add($e->next());
                $groupContentUserDAO = new OpenM_Book_Group_Content_UserDAO();
                OpenM_Log::debug("Check if user can view birthday", __CLASS__, __METHOD__, __LINE__);
                if ($groupContentUserDAO->isUserInGroups($userId, $visibilities->toArray())) {
                    $birthdayDisplayed = true;
                    if ($user->get(OpenM_Book_UserDAO::BIRTHDAY_YEAR_DISPLAYED)->toInt() == OpenM_Book_UserDAO::ACTIVE)
                        $return->put(self::RETURN_USER_BIRTHDAY_PARAMETER, $date->toString("d/m/Y"));
                    else {
                        $return->put(self::RETURN_USER_BIRTHDAY_PARAMETER, $date->toString("d/m"));
                    }
                }
                else
                    OpenM_Log::debug("user cant view birthday", __CLASS__, __METHOD__, __LINE__);
            }

            if ($isUserCalling || $birthdayDisplayed) {
                if ($user->get(OpenM_Book_UserDAO::BIRTHDAY_YEAR_DISPLAYED)->toInt() == OpenM_Book_UserDAO::ACTIVE)
                    $return->put(self::RETURN_USER_BIRTHDAY_DISPLAY_YEAR_PARAMETER, self::TRUE_PARAMETER_VALUE);
                else
                    $return->put(self::RETURN_USER_BIRTHDAY_DISPLAY_YEAR_PARAMETER, self::FALSE_PARAMETER_VALUE);
            }

            if ($isUserCalling)
                $return->put(self::RETURN_USER_PROPERTY_VALUE_VISIBILITY_PARAMETER, $user->get(OpenM_Book_UserDAO::BIRTHDAY_VISIBILITY)->toInt());

            OpenM_Log::debug("Check user property in DAO", __CLASS__, __METHOD__, __LINE__);
            $userPropertiesValueDAO = new OpenM_Book_User_Property_ValueDAO();

            OpenM_Log::debug("search Properties from user in DAO", __CLASS__, __METHOD__, __LINE__);
            $values = $userPropertiesValueDAO->getFromUser($userId, $userIdCalling);

            if ($values != null) {
                OpenM_Log::debug("Properties found in DAO", __CLASS__, __METHOD__, __LINE__);
                $e = $values->keys();
                $i = 0;
                while ($e->hasNext()) {
                    $key = $e->next();
                    $value = $values->get($key);
                    $propertyValue = new HashtableString();
                    $propertyValue->put(self::RETURN_USER_PROPERTY_ID_PARAMETER, $value->get(OpenM_Book_User_PropertyDAO::ID)->toInt());
                    $propertyValue->put(self::RETURN_USER_PROPERTY_NAME_PARAMETER, $value->get(OpenM_Book_User_PropertyDAO::NAME));
                    if ($value->get(OpenM_Book_User_Property_ValueDAO::ID) != "") {
                        $propertyValue->put(self::RETURN_USER_PROPERTY_VALUE_ID_PARAMETER, $value->get(OpenM_Book_User_Property_ValueDAO::ID)->toInt())
                                ->put(self::RETURN_USER_PROPERTY_VALUE_PARAMETER, $value->get(OpenM_Book_User_Property_ValueDAO::VALUE));
                        if (intval("$userId") === intval("$userIdCalling"))
                            $propertyValue->put(self::RETURN_USER_PROPERTY_VALUE_VISIBILITY_PARAMETER, $value->get(OpenM_Book_User_Property_ValueDAO::VISIBILITY)->toInt());
                    }
                    $propertyList->put($i, $propertyValue);
                    $i++;
                }
                if ($propertyList->size() > 0)
                    $return->put(self::RETURN_USER_PROPERTY_LIST_PARAMETER, $propertyList);
            }
            else
                OpenM_Log::debug("Property not found in DAO", __CLASS__, __METHOD__, __LINE__);
        }

        return $return
                        ->put(self::RETURN_USER_ID_PARAMETER, $user->get(OpenM_Book_UserDAO::ID))
                        ->put(self::RETURN_USER_FIRST_NAME_PARAMETER, $user->get(OpenM_Book_UserDAO::FIRST_NAME))
                        ->put(self::RETURN_USER_LAST_NAME_PARAMETER, $user->get(OpenM_Book_UserDAO::LAST_NAME));
    }

    /**
     * OK
     */
    public function registerMe($firstName, $lastName, $birthDay, $mail) {
        if (!String::isString($firstName))
            return $this->error("firstName must be a string", self::RETURN_ERROR_CODE_FIRST_NAME_BAD_FORMAT_VALUE);
        if (!RegExp::preg("/^[a-zA-Z]([a-zA-Z]|[ \t])+[a-zA-Z]?$/", OpenM_Book_Tool::strlwr($firstName)))
            return $this->error("firstName in bad format", self::RETURN_ERROR_CODE_FIRST_NAME_BAD_FORMAT_VALUE);
        if (!String::isString($lastName))
            return $this->error("lastName must be a string", self::RETURN_ERROR_CODE_LAST_NAME_BAD_FORMAT_VALUE);
        if (!RegExp::preg("/^[a-zA-Z]([a-zA-Z]|[ \t])+[a-zA-Z]?$/", OpenM_Book_Tool::strlwr($lastName)))
            return $this->error("lastName in bad format", self::RETURN_ERROR_CODE_LAST_NAME_BAD_FORMAT_VALUE);
        if (!String::isString($birthDay) && !is_numeric($birthDay))
            return $this->error("birthDay must be a string or a numeric", self::RETURN_ERROR_CODE_BIRTHDAY_BAD_FORMAT_VALUE);
        if ($birthDay instanceof String)
            $birthDay = "$birthDay";
        if (!OpenM_MailTool::isEMailValid($mail))
            return $this->error("mail must be in a valid format", self::RETURN_ERROR_CODE_MAIL_BAD_FORMAT_VALUE);
        $birthDay = intval($birthDay);
        $birthDayDate = new Date($birthDay);
        if ($birthDayDate->compareTo(Date::now()->less(Delay::years(self::AGE_LIMIT_TO_REGISTER))) > 0)
            return $this->error(self::RETURN_ERROR_MESSAGE_YOU_ARE_TOO_YOUNG_VALUE, self::RETURN_ERROR_CODE_TOO_YOUNG_VALUE);
        if ($birthDayDate->compareTo(Date::now()->less(Delay::years(self::AGE_MAX_TO_REGISTER))) < 0)
            return $this->error(self::RETURN_ERROR_MESSAGE_YOU_ARE_TOO_OLD_VALUE, self::RETURN_ERROR_CODE_TOO_OLD_VALUE);

        $userUID = $this->getManager()->getID();

        $userDAO = new OpenM_Book_UserDAO();
        OpenM_Log::debug("search user in DAO", __CLASS__, __METHOD__, __LINE__);
        $user = $userDAO->getFromUID($userUID);

        if ($user != null)
            return $this->error(self::RETURN_ERROR_MESSAGE_USER_ALREADY_REGISTERED_VALUE);
        OpenM_Log::debug("user not found in DAO", __CLASS__, __METHOD__, __LINE__);

        $groupDAO = new OpenM_Book_GroupDAO();
        OpenM_Log::debug("create personal group in DAO", __CLASS__, __METHOD__, __LINE__);
        $group = $groupDAO->create("personnal");
        OpenM_Log::debug("create birthday visibility group in DAO", __CLASS__, __METHOD__, __LINE__);
        $groupVisibility = $groupDAO->create("birthday visibility");
        OpenM_Log::debug("create user in DAO", __CLASS__, __METHOD__, __LINE__);
        $newUser = $userDAO->create($userUID, $firstName, $lastName, $birthDay, $mail, $group->get(OpenM_Book_GroupDAO::ID), $groupVisibility->get(OpenM_Book_GroupDAO::ID));

        OpenM_Log::debug("index user", __CLASS__, __METHOD__, __LINE__);
        $searchDAO = new OpenM_Book_SearchDAO();
        $searchDAO->index($firstName . " " . $lastName, $newUser->get(OpenM_Book_UserDAO::ID), OpenM_Book_SearchDAO::TYPE_USER);
        return $this->ok();
    }

    public function invitPeople($mailJSONList) {
        return $this->notImplemented();
    }

}

?>