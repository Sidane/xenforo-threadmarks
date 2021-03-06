<?php

class Sidane_Threadmarks_XenForo_ControllerPublic_Post extends XFCP_Sidane_Threadmarks_XenForo_ControllerPublic_Post
{
  public function actionThreadmark()
  {
    $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

    /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
    $ftpHelper = $this->getHelper('ForumThreadPost');
    list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
      $postId
    );

    $threadmarksModel = $this->_getThreadmarksModel();
    $existingThreadmark = $threadmarksModel->getByPostId($post['post_id']);
    if ($existingThreadmark)
    {
      $existingThreadmark = $threadmarksModel->prepareThreadmark(
        $existingThreadmark,
        $thread,
        $forum
      );
    }

    $threadmarkCategories = $threadmarksModel->getAllThreadmarkCategories();
    $threadmarkCategories = $threadmarksModel->prepareThreadmarkCategories(
      $threadmarkCategories
    );

    if ($this->_request->isPost())
    {
      $input = $this->_input->filter(array(
        'label' => XenForo_Input::STRING,
        'threadmark_category_id' => XenForo_Input::UINT
      ));

      if ($input['label'] === '')
      {
        return $this->responseError(new XenForo_Phrase(
          'please_enter_label_for_threadmark'
        ));
      }

      if ($existingThreadmark)
      {
        if ($this->isConfirmedPost())
        {
          if (!$existingThreadmark['canDelete'])
          {
            throw $this->getErrorOrNoPermissionResponseException(
              'you_do_not_have_permission_to_delete_threadmark'
            );
          }

          $threadmarksModel->deleteThreadmark($existingThreadmark);

          $phrase = 'threadmark_deleted';

          $existingCategoryId = $existingThreadmark['threadmark_category_id'];
          $existingCategory = array('title' => 'Unknown Category');
          if (!empty($threadmarkCategories[$existingCategoryId]))
          {
            $existingCategory = $threadmarkCategories[$existingCategoryId];
          }

          XenForo_Model_Log::logModeratorAction(
            'post',
            $post,
            'delete_threadmark_2',
            array(
              'category' => (string)$existingCategory['title'],
              'label' => $existingThreadmark['label']
            ),
            $thread
          );
        } else
        {
          if (!$existingThreadmark['canEdit'])
          {
            throw $this->getErrorOrNoPermissionResponseException(
              'you_do_not_have_permission_to_edit_threadmark'
            );
          }

          $threadmarksModel->setThreadMark(
            $thread,
            $post,
            $input['label'],
            $input['threadmark_category_id']
          );

          $phrase = 'threadmark_updated';

          $existingCategoryId = $existingThreadmark['threadmark_category_id'];
          $existingCategory = array('title' => 'Unknown Category');
          if (!empty($threadmarkCategories[$existingCategoryId]))
          {
            $existingCategory = $threadmarkCategories[$existingCategoryId];
          }

          $newCategoryId = $input['threadmark_category_id'];
          $newCategory = array('title' => 'Unknown Category');
          if (!empty($threadmarkCategories[$newCategoryId]))
          {
            $newCategory = $threadmarkCategories[$newCategoryId];
          }

          XenForo_Model_Log::logModeratorAction(
            'post',
            $post,
            'update_threadmark_2',
            array(
              'old_category' => (string)$existingCategory['title'],
              'old_label' => $existingThreadmark['label'],
              'new_category' => (string)$newCategory['title'],
              'new_label' => $input['label']
            ),
            $thread
          );
        }
      } else
      {
        if (!$threadmarksModel->canAddThreadmark($post, $thread, $forum))
        {
          throw $this->getErrorOrNoPermissionResponseException(
            'you_do_not_have_permission_to_add_threadmarks'
          );
        }

        $resetNesting = $this->_input->filterSingle(
          'reset_nesting',
          XenForo_Input::BOOLEAN
        );

        $position = $this->_input->filterSingle('position', XenForo_Input::UINT);
        if ($position === 0)
        {
          $position = false;
        }

        $threadmarksModel->setThreadMark(
          $thread,
          $post,
          $input['label'],
          $input['threadmark_category_id'],
          $position,
          $resetNesting
        );

        $phrase = 'threadmark_created';

        $categoryId = $input['threadmark_category_id'];
        $category = array('title' => 'Unknown Category');
        if (!empty($threadmarkCategories[$categoryId]))
        {
          $category = $threadmarkCategories[$categoryId];
        }

        XenForo_Model_Log::logModeratorAction(
          'post',
          $post,
          'create_threadmark_2',
          array(
            'category' => (string)$category['title'],
            'label' => $input['label']
          ),
          $thread
        );
      }

      $controllerResponse = $this->getPostSpecificRedirect($post, $thread);
      $controllerResponse->redirectMessage = new XenForo_Phrase($phrase);

      return $controllerResponse;
    } else
    {
      $threadmarkCategoryOptions = $threadmarksModel
        ->getThreadmarkCategoryOptions($threadmarkCategories, true);

      $viewParams = array(
        'post' => $post,
        'thread' => $thread,
        'forum' => $forum,
        'threadmarkCategoryOptions' => $threadmarkCategoryOptions,
        'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
      );

      if ($existingThreadmark)
      {
        if (!$existingThreadmark['canEdit'] && !$existingThreadmark['canDelete'])
        {
          throw $this->getErrorOrNoPermissionResponseException(
            'you_do_not_have_permission_to_edit_threadmark'
          );
        }
        $viewParams['threadmark'] = $existingThreadmark;
        $templateName = 'edit_threadmark';
      } else
      {
        if (!$threadmarksModel->canAddThreadmark($post, $thread, $forum))
        {
          throw $this->getErrorOrNoPermissionResponseException(
            'you_do_not_have_permission_to_add_threadmarks'
          );
        }
        $templateName = 'new_threadmark';
      }

      return $this->responseView(
        'Sidane_Threadmarks_ViewPublic_Post_Threadmark',
        $templateName,
        $viewParams
      );
    }
  }

  public function actionThreadmarkHistory()
  {
    $this->_request->setParam('content_type', 'threadmark');
    $this->_request->setParam(
      'content_id',
      $this->_input->filterSingle('post_id', XenForo_Input::UINT)
    );

    return $this->responseReroute(
      'XenForo_ControllerPublic_EditHistory',
      'index'
    );
  }

  public function actionThreadmarkPositionFill()
  {
    $this->_assertPostOnly();

    $postId = $this->_input->filterSingle(
      'post_id',
      XenForo_Input::UINT
    );
    $threadmarkCategoryId = $this->_input->filterSingle(
      'category_id',
      XenForo_Input::UINT
    );

    /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
    $ftpHelper = $this->getHelper('ForumThreadPost');
    list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
      $postId
    );

    $threadmarksModel = $this->_getThreadmarksModel();

    $threadmarkCategory = $threadmarksModel->getThreadmarkCategoryById(
      $threadmarkCategoryId
    );

    if (empty($threadmarkCategory))
    {
      return $this->responseError(
        new XenForo_Phrase('requested_page_not_found'),
        404
      );
    }

    if (!$threadmarksModel->canAddThreadmark($post, $thread, $forum))
    {
      throw $this->getErrorOrNoPermissionResponseException(
        'you_do_not_have_permission_to_add_threadmarks'
      );
    }

    $previousThreadmarkData = false;
    $lastThreadmarkData = false;

    $previousThreadmark = $threadmarksModel->getPreviousThreadmarkByPost(
      $threadmarkCategory['threadmark_category_id'],
      $thread['thread_id'],
      $post['position']
    );
    if (!empty($previousThreadmark))
    {
      $link = XenForo_Link::buildPublicLink(
        'threads/post-permalink',
        $thread,
        array('post' => $previousThreadmark)
      );

      $previousThreadmarkData = array(
        'position' => $previousThreadmark['threadmark_position'],
        'label' => $previousThreadmark['label'],
        'link' => $link
      );
    }

    $lastThreadmark = $threadmarksModel->getPreviousThreadmarkByLocation(
      $threadmarkCategory['threadmark_category_id'],
      $thread['thread_id']
    );
    if (!empty($lastThreadmark))
    {
      $link = XenForo_Link::buildPublicLink(
        'threads/post-permalink',
        $thread,
        array('post' => $lastThreadmark)
      );

      $lastThreadmarkData = array(
        'position' => $lastThreadmark['threadmark_position'],
        'label' => $lastThreadmark['label'],
        'link' => $link
      );
    }

    $this->_routeMatch->setResponseType('json');

    $viewParams = array(
      'previousThreadmark' => $previousThreadmarkData,
      'lastThreadmark' => $lastThreadmarkData
    );

    return $this->responseView(
      'XenForo_ViewPublic_Post_ThreadmarkPositionFill',
      '',
      $viewParams
    );
  }

  public function actionThreadmarkPreview()
  {
    $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
    /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
    $ftpHelper = $this->getHelper('ForumThreadPost');

    try
    {
      list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
        $postId,
        array('join' => XenForo_Model_Post::FETCH_USER)
      );
    } catch (Exception $e)
    {
      return $this->responseView(
        'XenForo_ViewPublic_Thread_Preview',
        '',
        array('post' => false)
      );
    }

    $threadmarksModel = $this->_getThreadmarksModel();
    $threadmark = $threadmarksModel->getByPostId($post['post_id']);

    if (empty($threadmark))
    {
      return $this->responseView(
        'XenForo_ViewPublic_Thread_Preview',
        '',
        array('post' => false)
      );
    }

    $viewParams = array(
      'threadmark' => $threadmark,
      'post' => $post,
      'thread' => $thread,
      'forum' => $forum
    );

    return $this->responseView(
      'XenForo_ViewPublic_Thread_Preview',
      'threadmark_preview',
      $viewParams
    );
  }

  public function actionPreviousThreadmark()
  {
    $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

    /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
    $ftpHelper = $this->getHelper('ForumThreadPost');
    list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
      $postId
    );

    $threadmarksModel = $this->_getThreadmarksModel();
    $threadmark = $threadmarksModel->getByPostId($post['post_id']);
    if ($threadmark)
    {
      $previousThreadmark = $threadmarksModel->getPreviousThreadmark($threadmark);
      list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
        @$previousThreadmark['post_id']
      );
    }

    return $this->getPostSpecificRedirect($post, $thread);
  }

  public function actionNextThreadmark()
  {
    $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

    /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
    $ftpHelper = $this->getHelper('ForumThreadPost');
    list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
      $postId
    );

    $threadmarksModel = $this->_getThreadmarksModel();
    $threadmark = $threadmarksModel->getByPostId($post['post_id']);
    if ($threadmark)
    {
      $nextThreadmark = $threadmarksModel->getNextThreadmark($threadmark);
      list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable(
        @$nextThreadmark['post_id']
      );
    }

    return $this->getPostSpecificRedirect($post, $thread);
  }

  /**
   * @return XenForo_Model|Sidane_Threadmarks_Model_Threadmarks
   */
  protected function _getThreadmarksModel()
  {
    return $this->getModelFromCache('Sidane_Threadmarks_Model_Threadmarks');
  }
}

if (false)
{
  class XFCP_Sidane_Threadmarks_XenForo_ControllerPublic_Post extends XenForo_ControllerPublic_Post {}
}
