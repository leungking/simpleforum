<?php
namespace app\controllers\admin;

use Yii;
use app\models\Topic;
use app\models\Node;
use app\models\Tag;
use yii\data\Pagination;
use yii\helpers\ArrayHelper;

class TopicManagerController extends CommonController
{
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
                  ->andWhere(['{{%tag_topic}}.tag_id' => $tag_id]);
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

        return $this->render('@app/plugins/TopicManager/views/index', [
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
}
