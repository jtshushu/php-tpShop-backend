<?php

namespace Goods\Model;

use Think\Model;

class CategoryModel extends Model {

    protected $_validate = array(
        array('cat_name', 'require', '分类名称不能为空', 1),
        array('cat_name', '', '分类名称不能重复', 1, 'unique'),
    );

    public function search() {
        $pages = 15;

        $where = 1;

        $count = $this->where($where)->count(); // 查询满足要求的总记录数
        $Page = new \Think\Page($count, $pages); // 实例化分页类 传入总记录数和每页显示的记录数(25)

        $data['show'] = $Page->show(); // 分页显示输出

        $data['list'] = $this->order('id')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //echo $this->getLastSql();die;
        return $data;
    }

    public function get_category($cat_id = 0) {
        $data = $this->select();

        return $this->get_tree($data, $cat_id);
    }

    public function get_tree($data, $parent_id = 0, $level = 0) {
        static $arr = array();
        foreach ($data as $d) {
            if ($d['parent_id'] == $parent_id) {
                $d['level'] = $level;
                $arr[] = $d;

                $this->get_tree($data, $d['id'], $level + 1);
            }
        }

        return $arr;
    }
    //删除商品分类时,为0的报错
    public function _before_delete($options) {
       //将商品分类中的所有数据,检索出来 
        $data = $this->select();
        //删除时,判断是否是删除多个商品分类,多个时就是数组                    
        if (is_array($options['where']['id'])) {

            $arr = explode(',', $options['where']['id'][1]);
            $arr2 = array();
            foreach ($arr as $a) {
                $tree = $this->delete_tree($data, $a);
                $arr2 = array_merge($arr2, $tree); //将所有的数组取出合并到一个数组中去
            }
            $arr2 = array_unique($arr2);
            $str = implode(',', $arr2);

            $this->execute("delete from sh_category where id in ( $str )");
        } else {
            //取出要删除的商品分类的下级分类,返回的可能是一个空数组
            $del = $this->delete_tree($data, $options['where']['id']);
            $str=array();
            //将要删除的商品分类id加入到数组中
            array_push($str,$options['where']['id']); 
            //如果返回的下级分类为空,说明没有下级分类,那么用空格分隔$str,否则是数组,用,号分隔
            if(!empty($del))
            $str = implode(',', $del);    
            else
                $str=  implode ('', $str);
            $this->execute("delete from sh_category where id in ( $str )");
        }
    }
    //将当前要删除的id的,子商品分类一并删除掉
    public function delete_tree($data, $parent_id) {
        $arr = array();
        //循环商品分类,取出要删除商品分类下的分类
        foreach ($data as $c) {
            if ($c['parent_id'] == $parent_id) {
                $arr[] = $c['id'];
                $arr = array_merge($arr,$this->delete_tree($data,$c['id']));
            }
        }
        return $arr;
    }

    public function _after_insert($data, $options) {
        $rec = I('post.rec'); //添加之前,先向商品推荐分类表插入
        if ($rec) {
            $rec_item = M('recommendItem');
            foreach ($rec as $r) {
                $rec_item->add(array(
                    'rec_id' => $r['id'],
                    'goods_id' => $data['id']
                ));
            }
        }
    }

    public function _before_update(&$data, $options) {
        //修改推荐位后,处理表单
        $rec = I('post.rec');
        $rec_item = M('recommendItem');
        if ($rec) {
            
            $rec_item->where('goods_id=' . $options['where']['id'] . ' and rec_id in(select id from sh_recommend where rec_type="分类")')->delete();
            foreach ($rec as $r) {
                $rec_item->add(array(
                    'goods_id' => $options['where']['id'],
                    'rec_id' => $r
                ));
            }
        } else {//如果将分类的勾选取消后,发送过来的就是空字符串,意思就是全删除掉
            $rec_item->where('goods_id=' . $options['where']['id'] . ' and rec_id in(select id from sh_recommend where rec_type="分类")')->delete();
        }
    }

}
