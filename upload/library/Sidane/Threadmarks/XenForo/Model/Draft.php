<?php

class Sidane_Threadmarks_XenForo_Model_Draft extends XFCP_Sidane_Threadmarks_XenForo_Model_Draft
{
  public function saveDraft(
    $key,
    $message,
    array $extraData = array(),
    array $viewingUser = null,
    $lastUpdate = null
  )
  {
    if (!empty(Sidane_Threadmarks_Globals::$threadmarkLabel))
    {
      $extraData['threadmark'] = Sidane_Threadmarks_Globals::$threadmarkLabel;
    }

    if (!empty(Sidane_Threadmarks_Globals::$threadmarkCategoryId))
    {
      $extraData['threadmark_category_id'] = Sidane_Threadmarks_Globals::$threadmarkCategoryId;
    }

    return parent::saveDraft($key, $message, $extraData, $viewingUser, $lastUpdate);
  }
}

if (false)
{
  class XFCP_Sidane_Threadmarks_XenForo_Model_Draft extends XenForo_Model_Draft {}
}
