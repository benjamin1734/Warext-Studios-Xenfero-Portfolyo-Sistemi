<?php

namespace Warext\Portfolio\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Item extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canView())
        {
            return $this->noPermission();
        }

        $repo = $this->repository('Warext\Portfolio:Portfolio');
        $community = $this->service('Warext\Portfolio:Community');
        $community->recordView($portfolio);
        return $this->view('Warext\Portfolio:Portfolio\\View', 'wrxt_portfolio_view', [
            'portfolio' => $portfolio,
            'galleryFiles' => $repo->getGalleryFiles($portfolio->portfolio_id, (string)$portfolio->status !== 'published'),
            'modelFile' => $this->findSafeModelFile((int)$portfolio->portfolio_id),
            'comments' => $repo->getComments((int)$portfolio->portfolio_id),
            'communityState' => $community->visitorState($portfolio),
            'canonicalUrl' => $this->buildLink('canonical:portfolyo/calisma', $portfolio),
            'tags' => $portfolio->getTagList()
        ]);
    }

    public function actionEdit(ParameterBag $params)
    {
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canEdit())
        {
            return $this->noPermission();
        }

        return $this->view('Warext\Portfolio:Portfolio\\Edit', 'wrxt_portfolio_edit', [
            'portfolio' => $portfolio,
            'categories' => $this->repository('Warext\Portfolio:Portfolio')->getActiveCategories(),
            'files' => $this->repository('Warext\Portfolio:Portfolio')->getEditableFiles($portfolio->portfolio_id),
            'quota' => $this->service('Warext\Portfolio:QuotaPolicy')->getPolicy($portfolio->User ?: \XF::visitor())
        ]);
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canEdit())
        {
            return $this->noPermission();
        }

        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'category_id' => 'uint',
            'portfolio_type' => 'str',
            'programs' => 'str',
            'tags' => 'str'
        ]);

        $service = $this->service('Warext\Portfolio:UpdatePortfolio', $portfolio);
        $service->setContent($input['title'], $input['description'], $input['category_id'], $input['portfolio_type'], $input['programs'], $input['tags']);
        if (!$service->validate($errors))
        {
            return $this->error($errors);
        }
        $service->save();
        return $this->redirect($this->buildLink('portfolyo/calisma', $portfolio));
    }

    public function actionDelete(ParameterBag $params)
    {
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canDelete())
        {
            return $this->noPermission();
        }

        if ($this->isPost())
        {
            $this->service('Warext\Portfolio:PortfolioDeletion')->delete($portfolio);
            return $this->redirect($this->buildLink('portfolyo/mine'));
        }

        return $this->view('Warext\Portfolio:Portfolio\\Delete', 'wrxt_portfolio_delete', [
            'portfolio' => $portfolio
        ]);
    }


    public function actionUpload(ParameterBag $params)
    {
        $this->assertPostOnly();
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canEdit())
        {
            return $this->noPermission();
        }

        $role = $this->filter('file_role', 'str');
        $upload = $this->request->getFile('portfolio_file', false, false);
        if (!$upload)
        {
            return $this->error(\XF::phrase('wrxt_portfolio_select_file'));
        }

        try
        {
            $this->service('Warext\Portfolio:Quarantine')->accept($portfolio, $upload, $role);
        }
        catch (\RuntimeException $e)
        {
            return $this->error($e->getMessage());
        }

        return $this->redirect($this->buildLink('portfolyo/calisma/edit', $portfolio));
    }

    public function actionFileDelete(ParameterBag $params)
    {
        $this->assertPostOnly();
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canEdit())
        {
            return $this->noPermission();
        }

        $fileId = $this->filter('file_id', 'uint');
        $file = $this->em()->find('Warext\Portfolio:PortfolioFile', $fileId);
        if (!$file || (int)$file->portfolio_id !== (int)$portfolio->portfolio_id || (int)$file->user_id !== (int)$portfolio->user_id)
        {
            return $this->notFound();
        }
        if ((string)$portfolio->status === 'published' && (string)$file->state === 'published')
        {
            return $this->error(\XF::phrase('wrxt_portfolio_published_file_delete_requires_moderation'));
        }

        if ($file->storage_name)
        {
            \XF\Util\File::deleteFromAbstractedPath($file->storage_name);
            $file->storage_name = '';
            $file->save();
        }
        $this->service('Warext\Portfolio:BlobManager')->cleanupStaging($file);
        $this->service('Warext\Portfolio:BlobManager')->detachFile($file);
        if ($file->state !== 'deleted')
        {
            (new \Warext\Portfolio\Service\StateMachine())->transitionFile($file, 'deleted', 'user_removed');
        }
        if ((int)$portfolio->cover_file_id === (int)$file->file_id)
        {
            $portfolio->cover_file_id = 0;
        }
        if ((int)$portfolio->model_file_id === (int)$file->file_id)
        {
            $portfolio->model_file_id = 0;
        }
        $portfolio->saveIfChanged();
        $this->service('Warext\Portfolio:PortfolioSecurityState')->refresh($portfolio);

        return $this->redirect($this->buildLink('portfolyo/calisma/edit', $portfolio));
    }

    public function actionModelFrame(ParameterBag $params)
    {
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canView())
        {
            return $this->noPermission();
        }
        $file = $this->findSafeModelFile((int)$portfolio->portfolio_id);
        if (!$file)
        {
            return $this->notFound();
        }

        $expires = \XF::$time + 900;
        $token = $this->makeModelToken($file, $expires);
        $dataUrl = $this->buildLink('canonical:portfolyo/calisma/model-data', $portfolio, [
            'file_id' => (int)$file->file_id,
            'expires' => $expires,
            'token' => $token
        ]);
        $boardUrl = rtrim((string)\XF::options()->boardUrl, '/');
        $parts = parse_url($boardUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');

        $this->setResponseType('raw');
        return $this->view('Warext\Portfolio:Model\Frame', '', [
            'dataUrl' => $dataUrl,
            'scriptUrl' => $boardUrl . '/js/warext/portfolio/model-viewer.js?v=1000100',
            'origin' => $origin
        ]);
    }

    public function actionModelData(ParameterBag $params)
    {
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if ((string)$portfolio->status === 'deleted')
        {
            return $this->notFound();
        }
        $fileId = $this->filter('file_id', 'uint');
        $expires = $this->filter('expires', 'uint');
        $token = $this->filter('token', 'str');
        $file = $this->em()->find('Warext\Portfolio:PortfolioFile', $fileId);
        if (!$file || (int)$file->portfolio_id !== (int)$portfolio->portfolio_id || (string)$file->file_role !== 'model')
        {
            return $this->notFound();
        }
        if ($expires < \XF::$time || $expires > \XF::$time + 1800 || !hash_equals($this->makeModelToken($file, $expires), $token))
        {
            return $this->noPermission();
        }
        $allowedStates = (string)$portfolio->status === 'published' ? ['published'] : ['security_passed', 'moderation', 'published'];
        if ((string)$portfolio->status === 'published' && (int)$portfolio->model_file_id !== (int)$file->file_id)
        {
            return $this->notFound();
        }
        if (!in_array((string)$file->state, $allowedStates, true) || (string)$file->processing_status !== 'passed' || (string)$file->processed_mime !== 'model/gltf-binary')
        {
            return $this->notFound();
        }
        if (!$file->ProcessedBlob || (string)$file->ProcessedBlob->state !== 'ready' || (string)$file->ProcessedBlob->security_state === 'blocked') { return $this->notFound(); }
        $storageName = $this->service('Warext\Portfolio:BlobManager')->primaryStorageName($file);
        if ($storageName === '')
        {
            return $this->notFound();
        }
        $stream = \XF::fs()->readStream($storageName);
        if (!is_resource($stream))
        {
            return $this->notFound();
        }
        $content = stream_get_contents($stream);
        fclose($stream);
        if (!is_string($content) || strlen($content) !== (int)$file->processed_size)
        {
            return $this->notFound();
        }
        $this->setResponseType('raw');
        return $this->view('Warext\Portfolio:Model\Data', '', ['content' => $content]);
    }


    public function actionLike(ParameterBag $params)
    {
        $this->assertPostOnly(); $portfolio=$this->assertPortfolio($params->portfolio_id); if(!$portfolio->canView()) return $this->noPermission();
        try{$this->service('Warext\Portfolio:Community')->toggleLike($portfolio);}catch(\RuntimeException $e){return $this->error(\XF::phrase($e->getMessage()));}
        return $this->redirect($this->buildLink('portfolyo/calisma',$portfolio));
    }

    public function actionSaveItem(ParameterBag $params)
    {
        $this->assertPostOnly(); $portfolio=$this->assertPortfolio($params->portfolio_id); if(!$portfolio->canView()) return $this->noPermission();
        try{$this->service('Warext\Portfolio:Community')->toggleSave($portfolio);}catch(\RuntimeException $e){return $this->error(\XF::phrase($e->getMessage()));}
        return $this->redirect($this->buildLink('portfolyo/calisma',$portfolio));
    }

    public function actionComment(ParameterBag $params)
    {
        $this->assertPostOnly(); $portfolio=$this->assertPortfolio($params->portfolio_id); if(!$portfolio->canView()) return $this->noPermission();
        $message=$this->filter('message','str');
        try{$this->service('Warext\Portfolio:Community')->addComment($portfolio,$message);}catch(\RuntimeException $e){return $this->error(\XF::phrase($e->getMessage()));}
        return $this->redirect($this->buildLink('portfolyo/calisma',$portfolio));
    }

    public function actionCommentDelete(ParameterBag $params)
    {
        $this->assertPostOnly(); $portfolio=$this->assertPortfolio($params->portfolio_id); $commentId=$this->filter('comment_id','uint');
        $comment=$this->em()->find('Warext\Portfolio:Comment',$commentId); if(!$comment||(int)$comment->portfolio_id!==(int)$portfolio->portfolio_id) return $this->notFound();
        try{$this->service('Warext\Portfolio:Community')->deleteComment($comment);}catch(\RuntimeException $e){return $this->error(\XF::phrase($e->getMessage()));}
        return $this->redirect($this->buildLink('portfolyo/calisma',$portfolio));
    }

    public function actionReport(ParameterBag $params)
    {
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canReport()) { return $this->noPermission(); }
        if ($this->isPost())
        {
            $this->assertPostOnly();
            $reason = $this->filter('reason_code', 'str');
            $message = $this->filter('message', 'str');
            $fileId = $this->filter('file_id', 'uint');
            try { $this->service('Warext\\Portfolio:ModerationManager')->createReport($portfolio, $reason, $message, $fileId); }
            catch (\RuntimeException $e) { return $this->error(\XF::phrase($e->getMessage())); }
            return $this->redirect($this->buildLink('portfolyo/calisma', $portfolio));
        }
        return $this->view('Warext\\Portfolio:Portfolio\\Report', 'wrxt_portfolio_report', ['portfolio' => $portfolio]);
    }

    public function actionMedia(ParameterBag $params)
    {
        $portfolio=$this->assertPortfolio($params->portfolio_id); if(!$portfolio->canView()) return $this->noPermission();
        $fileId=$this->filter('file_id','uint'); $thumb=$this->filter('thumb','bool'); $file=$this->em()->find('Warext\Portfolio:PortfolioFile',$fileId);
        $allowedStates = (string)$portfolio->status === 'published' ? ['published'] : ['security_passed','moderation','published'];
        if(!$file||(int)$file->portfolio_id!==(int)$portfolio->portfolio_id||!in_array((string)$file->file_role,['cover','gallery'],true)||!in_array((string)$file->state,$allowedStates,true)||(string)$file->processing_status!=='passed'||(string)$file->processed_mime!=='image/webp') return $this->notFound();
        if (!$file->ProcessedBlob || (string)$file->ProcessedBlob->state !== 'ready' || (string)$file->ProcessedBlob->security_state === 'blocked' || ($thumb && (!$file->ThumbnailBlob || (string)$file->ThumbnailBlob->state !== 'ready' || (string)$file->ThumbnailBlob->security_state === 'blocked'))) return $this->notFound();
        $storageName = $thumb ? ($file->ThumbnailBlob ? (string)$file->ThumbnailBlob->storage_name : (string)$file->thumbnail_storage_name) : $this->service('Warext\Portfolio:BlobManager')->primaryStorageName($file);
        if($storageName==='') return $this->notFound(); $stream=\XF::fs()->readStream($storageName); if(!is_resource($stream)) return $this->notFound(); $content=stream_get_contents($stream); fclose($stream); if(!is_string($content)) return $this->notFound();
        $this->setResponseType('raw'); return $this->view('Warext\Portfolio:Media','',['content'=>$content,'etag'=>$file->processed_sha256 . ($thumb?'-t':'')]);
    }

    protected function findSafeModelFile(int $portfolioId)
    {
        $portfolio = $this->em()->find('Warext\Portfolio:Portfolio', $portfolioId, ['ModelFile']);
        if (!$portfolio)
        {
            return null;
        }
        if ((string)$portfolio->status === 'published')
        {
            $file = $portfolio->ModelFile;
            if (!$file || (string)$file->file_role !== 'model' || (string)$file->state !== 'published' || (string)$file->processing_status !== 'passed' || (string)$file->processed_mime !== 'model/gltf-binary' || !$file->ProcessedBlob || (string)$file->ProcessedBlob->state !== 'ready' || (string)$file->ProcessedBlob->security_state === 'blocked')
            {
                return null;
            }
            return $file;
        }
        $file = $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('portfolio_id', $portfolioId)
            ->where('file_role', 'model')
            ->where('state', ['security_passed', 'moderation', 'published'])
            ->where('processing_status', 'passed')
            ->with('ProcessedBlob')
            ->order('file_id', 'DESC')
            ->fetchOne();
        if (!$file || !$file->ProcessedBlob || (string)$file->ProcessedBlob->state !== 'ready' || (string)$file->ProcessedBlob->security_state === 'blocked')
        {
            return null;
        }
        return $file;
    }

    protected function makeModelToken($file, int $expires): string
    {
        $salt = (string)\XF::app()->config('globalSalt');
        return hash_hmac('sha256', (int)$file->file_id . '|' . $expires . '|' . (string)$file->processed_sha256, $salt);
    }

    protected function assertPortfolio(int $portfolioId)
    {
        return $this->assertRecordExists(
            'Warext\Portfolio:Portfolio',
            $portfolioId,
            ['User', 'Category', 'CoverFile', 'ModelFile'],
            'wrxt_portfolio_not_found'
        );
    }
}
