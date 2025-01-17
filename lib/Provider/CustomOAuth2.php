<?php

namespace OCA\SocialLogin\Provider;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Data;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\User;

class CustomOAuth2 extends OAuth2
{
    /**
     * @return User\Profile
     * @throws UnexpectedApiResponseException
     * @throws \Hybridauth\Exception\HttpClientFailureException
     * @throws \Hybridauth\Exception\HttpRequestFailedException
     * @throws \Hybridauth\Exception\InvalidAccessTokenException
     */
    public function getUserProfile()
    {
        $profileFields = $this->strToArray($this->config->get('profile_fields'));
        $profileUrl = $this->config->get('endpoints')['profile_url'];

        if (count($profileFields) > 0) {
            $profileUrl .= (strpos($profileUrl, '?') !== false ? '&' : '?') . 'fields=' . implode(',', $profileFields);
        }

        $response = $this->apiRequest($profileUrl);
        if (!isset($response->identifier) && isset($response->id)) {
            $response->identifier = $response->id;
        }
        if (!isset($response->identifier) && isset($response->data->id)) {
            $response->identifier = $response->data->id;
        }
        if (!isset($response->identifier) && isset($response->user_id)) {
            $response->identifier = $response->user_id;
        }

        $data = new Data\Collection($response);

        if (!$data->exists('identifier')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();
        foreach ($data->toArray() as $key => $value) {
            if ($key !== 'data' && property_exists($userProfile, $key)) {
                $userProfile->$key = $value;
            }
        }

        $groups = $this->getGroups($data);
        if (isset($groups) && is_array($groups)) {
            $userProfile->data['groups'] = $groups;
        }
        if ($groupMapping = $this->config->get('group_mapping')) {
            $userProfile->data['group_mapping'] = $groupMapping;
        }

        return $userProfile;
    }

    protected function getGroups(Data\Collection $data)
    {
        $groupsClaim = $this->config->get('groups_claim');
        if (!empty($groupsClaim)) {
            if ($groups = $data->get($groupsClaim)) {
                if (is_array($groups)) {
                    return $groups;
                } elseif (is_string($groups)) {
                    return $this->strToArray($groups);
                }
            }
            return array();
        }
        return null;
    }

    private function strToArray($str)
    {
        return array_filter(
            array_map('trim', explode(',', $str)),
            function ($val) { return $val !== ''; }
        );
    }
}
