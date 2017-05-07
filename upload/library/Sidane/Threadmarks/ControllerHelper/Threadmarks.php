<?php

class Sidane_Threadmarks_ControllerHelper_Threadmarks extends XenForo_ControllerHelper_Abstract
{
  public function getRecentThreadmarks(array $thread, array $forum)
  {
    if (empty($thread['threadmark_count']))
    {
      return null;
    }

    $threadmarksModel = $this->_controller->getModelFromCache(
      'Sidane_Threadmarks_Model_Threadmarks'
    );

    if (!$threadmarksModel->canViewThreadmark($thread, $forum))
    {
      return null;
    }

    $menuLimit = $threadmarksModel->getMenuLimit($thread);

    $threadmarkCategories = $threadmarksModel->getRecentThreadmarksByThread(
      $thread,
      $forum,
      $menuLimit
    );

    if (empty($threadmarkCategories))
    {
      return null;
    }

    $threadmarkCategoryPositions = $threadmarksModel
      ->getThreadmarkCategoryPositionsByThread($thread);

    foreach ($threadmarkCategories as $threadmarkCategoryId => &$threadmarkCategory)
    {
      if (!empty($threadmarkCategoryPositions[$threadmarkCategoryId]))
      {
        $threadmarkCategory['count'] = $threadmarkCategoryPositions[$threadmarkCategoryId];
      }
      else
      {
        $threamdarkCategory['count'] = count($threadmarks['children']);
      }

      // $menuLimit: 0 = unlimited
      $threadmarkCategory['more_threadmarks'] = false;
      if (($menuLimit > 0) && ($threadmarkCategory['count'] > $menuLimit))
      {
        $threadmarkCategory['more_threadmarks'] = true;
      }
    }

    return $threadmarkCategories;
  }
}
