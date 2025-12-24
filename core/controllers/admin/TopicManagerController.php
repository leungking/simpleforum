<?php
/**
 * @link https://610000.xyz/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Leon admin@610000.xyz
 */

namespace app\controllers\admin;

use Yii;
use app\models\Topic;
use app\models\Node;
use app\models\Tag;
use yii\data\Pagination;
use yii\helpers\ArrayHelper;
use yii\filters\VerbFilter;

class TopicManagerController extends CommonController
{
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'batch-delete' => ['post'],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        $request = Yii::$app->getRequest();
        $node_id = $request->get('node_id');
        $tag_id = $request->get('tag_id');
        $q = $request->get('q');

        $query = Topic::find()->with(['node', 'author']);

        if (!empty($node_id)) {
            $query->andWhere(['node_id' => $node_id]);
        }

        if (!empty($tag_id)) {
            $query->innerJoin('{{%tag_topic}}', '{{%tag_topic}}.topic_id = {{%topic}}.id')
                  ->andWhere(['{{%tag_topic}}.tag_id' => $tag_id])
                  ->distinct();
        }

        if (!empty($q)) {
            $query->andWhere(['like', 'title', $q]);
        }

        $countQuery = clone $query;
        $pages = new Pagination([
            'totalCount' => $countQuery->count(),
            'pageSize' => 20,
        ]);

        $topics = $query->offset($pages->offset)
            ->limit($pages->limit)
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $nodes = ArrayHelper::map(Node::find()->asArray()->all(), 'id', 'name');
        $tags = ArrayHelper::map(Tag::find()->limit(100)->asArray()->all(), 'id', 'name');

        return $this->render('index', [
            'topics' => $topics,
            'pages' => $pages,
            'nodes' => $nodes,
            'tags' => $tags,
            'node_id' => $node_id,
            'tag_id' => $tag_id,
            'q' => $q,
        ]);
    }

    public function actionDelete($id)
    {
        $model = Topic::findOne($id);
        if ($model) {
            $model->delete();
            Yii::$app->getSession()->setFlash('success', Yii::t('app', 'Topic deleted.'));
        }
        return $this->redirect(['index']);
    }

    public function actionBatchDelete()
    {
        $ids = Yii::$app->getRequest()->post('ids');
        if (!empty($ids) && is_array($ids)) {
            $topics = Topic::findAll($ids);
            foreach ($topics as $topic) {
                $topic->delete();
            }
            Yii::$app->getSession()->setFlash('success', Yii::t('app', '{n} topics deleted.', ['n' => count($ids)]));
        }
        return $this->redirect(['index']);
    }
}
