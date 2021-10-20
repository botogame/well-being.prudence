<?php
namespace app\forms;

use std, gui, framework, app;
use php\jsoup\Jsoup;

class MainForm extends AbstractForm
{

    var $play_now = false;

    var $file_context = './catalog/Context.json';
    var $file_version = './catalog/Version.json';
    var $file_changes = './catalog/Changes.json';

    var $context = [];
    var $config = [];
    var $changes = [];

    var $names_index = [
        0 => 'Возрождение',
        1 => 'Блаженство',
        2 => 'Разложение',
        3 => 'Упадничество',
    ];
    var $arr_image_tab = [
        0=> 'dandelion.png',
        1=> 'palm-tree.png',
        2=> 'dry-leaf.png',
        3=> 'snowman.png',
    ];
    var $images_to_tree = [
        'level 0' => [
            0 => 'sun.png',
            1 => 'hurricane.png',
            2 => 'water-cycle.png',
            3 => 'soil.png',
        ],
        'level 1' => [
            0 => '1-sahasrara.png',
            1 => '2-ajna.png',
            2 => '3-vishuddha.png',
            3 => '4-anahata.png',
            4 => '5-manipura.png',
            5 => '6-svadhishthana.png',
            6 => '7-muladhara.png',
        ],
    ];
    var $save_tab = [];
    var $server_version = [];
    var $user_agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 YaBrowser/20.3.1.197 Yowser/2.5 Safari/537.36';
    public $thread;

    /**
     * @event construct
     */
    function doConstruct(UXEvent $e = null)
    {

        /*настройка*/
        $data = file_get_contents($this->file_version);

        $this->version = json_decode($data,1);

        $this->label_version->text = 'v. '.$this->version['Версия'];

        $this->load_context();
        $this->load_changes();


        /*проверяем обновления*/
        $this->thread = new Thread(function () {

            for($i=1;$i<=500;$i++){

                if($this->button_update->visible == false){


                    $data = Jsoup::connect($this->version['Ссылка обновления'].'Version.json')
                        ->method("GET")
                        ->userAgent($this->user_agent)
                        ->timeout(6000)
                        ->execute();

                    if(!$data){
                        uiLater(function() {
                            alert('Не получилось загрузить обновления с ссылки: '.$this->version['Ссылка обновления'].'Version.json');
                        });
                    }
                    else{

                        $this->server_version = json_decode($data->body(),1);

                        if(!isset($this->server_version['Версия'])){
                            uiLater(function() {
                                alert('Ошибка в распознании обновления с ссылки: '.$this->version['Ссылка обновления'].'Version.json');
                            });
                        }
                        elseif($this->server_version['Версия'] != $this->version['Версия']){
                            $this->button_update->visible = true;
                        }
                    }

                }

                sleep(3600);
            }



        });
        $this->thread->start();
        
    }

     /**
     * @event button_update.click-Left 
     */
    function doButton_updateClickLeft(UXMouseEvent $e = null)
    {

        $this->form('MainForm')->showPreloader('Обновление');

        $thread = new Thread(function () {

            if($this->version['Версия']!=$this->server_version['Версия']){


                $data = Jsoup::connect($this->version['Ссылка обновления'].$this->server_version['Версия'].'.jar')
                    ->method("GET")
                    ->userAgent($this->user_agent)
                    ->timeout(60000)
                    ->maxBodySize(1000*1000*10)
                    ->ignoreContentType(true)
                    ->execute();

                if(!$data){
                    uiLater(function() {
                        alert('Ошибка в загрузке файла с сервера!');
                        $this->form('MainForm')->hidePreloader();
                    });
                    return;
                }

                file_put_contents('./lib/dn-compiled-module.jar',$data->bodyAsBytes());

                /*
                 * не работает перезапуск
                if($answer = UXDialog::showAndWait('Программа перезапустится.')){
                    if($answer == "O"){
                        app()->hideForm('MainForm');
                        app()->showForm('MainForm');
                    }
                }*/


                $this->version['Версия'] = $this->server_version['Версия'];

                file_put_contents($this->file_version,json_encode($this->version));

                uiLater(function() {
                    UXDialog::showAndWait('Перезапустите программу для применения обновления!');
                });

            }

            $this->button_update->visible = false;
            uiLater(function() {
                $this->form('MainForm')->hidePreloader();
            });

        });
        $thread->start();

    }

    function save_change($do, $type, $patch, $parameters){

        if(!in_array($do,['Добавить','Удалить','Изменить','Загрузить'])){
            return false;
        }

        if(!in_array($type,['Деятельность','Ритм'])){
            return false;
        }

        if($do!='Загрузить'){
            $change = [
                'Время'=> time(),
                'Тип'=> $type,
                'Действие'=> $do,
                'Путь'=> $patch,
                'Параметры'=> $parameters,
            ];

            $change['Код'] = md5($change);

            $this->changes[] = $change;

            $this->load_changes_one($change);

            file_put_contents($this->file_changes,json_encode($this->changes));

            $this->list_changes->scrollTo(9999999);
        }

        file_put_contents($this->file_context,json_encode($this->context));
    }

    function load_changes_one($change){

        $text = (new Time($change['Время']*1000))->toString('dd/MM/yyyy HH:mm').' ';

        if($change['Действие']=='Добавить' and $change['Тип'] == 'Ритм'){
            $text.= 'добавлен ритм "'.$change['Параметры']['name_new'].'"';
        }
        elseif($change['Действие']=='Удалить' and $change['Тип'] == 'Ритм'){
            $text.= 'удалён ритм "'.$change['Параметры']['name_old'].'"';
        }
        elseif($change['Действие']=='Изменить' and $change['Тип'] == 'Ритм'){
            $text.= 'изменено название ритма с "'.$change['Параметры']['name_old'].'" на "'.$change['Параметры']['name_new'].'"';
        }
        elseif($change['Действие']=='Изменить' and $change['Тип'] == 'Деятельность'){
            if($change['Параметры']['additionally_old']!=''){
                $text.= 'изменена деятельность с "'.$change['Параметры']['additionally_old'].'" на "'.$change['Параметры']['additionally_new'].'"';
            }
            else{
                $text.= 'введена деятельность "'.$change['Параметры']['additionally_new'].'"';
            }
        }

        $keys = $change['Путь'];
        $name_index = $this->names_index[$keys[0]];

        $text.= ' для раздела "'.$name_index.' / '.$this->context[$name_index]['items'][$keys[1]]['name'].' / '.$this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['name'].'"';

        $this->list_changes->items->add($text);

    }

    function load_changes()
    {

        /*изменения*/
        $data = file_get_contents($this->file_changes);

        $this->changes = json_decode($data,1);

        if(count($this->changes)>0){

            foreach($this->changes as $change){
                $this->load_changes_one($change);
            }

        }

        $this->list_changes->scrollTo(9999999);

    }

    function load_context()
    {

        /*контент*/
        $data = file_get_contents($this->file_context);

        $this->context = json_decode($data,1);

        $this->save_tab['last']['id'] = '-1';

        $this->doTabPaneChange();

    }

    /**
     * @event tabPane.change
     */
    function doTabPaneChange(UXEvent $e = null)
    {

        $id = $this->tabPane->selectedIndex;
        $last_id = $this->save_tab['last']['id'];

        if($last_id!='-1' and $last_id != $id){
            $this->save_tab[$last_id]['search'] = $this->edit_search->text;
            $this->save_tab[$last_id]['tree'] = $this->tree->root;
        }

        if(in_array($id,[0,1,2,3])){


            $name_index = $this->names_index[$id];

            $this->label->text = $this->context[$name_index]['name'];


            if(isset($this->save_tab[$id]['search'])){
                $this->edit_search->text = $this->save_tab[$id]['search'];
            }
            else{
                $this->edit_search->text = '';
            }

            if(isset($this->save_tab[$id]['tree'])){
                $this->tree->root = $this->save_tab[$id]['tree'];
            }
            else{
                $this->tree->root = new UXTreeItem();
                $this->add_to_tree($this->tree->root, $this->context[$name_index]['items'], [$id]);
            }




        }

        $this->save_tab['last']['id'] = $id;

    }

    /**
     * @event mediaView.construct
     */
    function doMediaViewConstruct(UXEvent $e = null)
    {


        $this->label_video->text = 'Вибрации галактики';
        $this->mediaView->stop();
        $this->mediaView->open('./catalog/Video/background.mp4', true);
        $this->mediaView->player->cycleCount = 5000;
        $this->mediaView->player->volume = $this->slider_volume->value;
        $this->button_stop->text = "Выключить";

        $this->play_now = './catalog/Video/background.mp4';

    }

    /**
     * @event tree.click-Left
     */
    function doTreeClickLeft(UXMouseEvent $e = null)
    {
        if ($this->tree->selectedItems) {


            if($this->tree->selectedItems[0]->value->type == 'level_0'){

                $this->button_edit->enabled = false;
                $this->button_add->enabled = false;

            }
            elseif($this->tree->selectedItems[0]->value->type == 'level_1'){

                $this->button_edit->enabled = true;
                $this->button_add->enabled = true;

            }
            elseif($this->tree->selectedItems[0]->value->type == 'level_2'){

                $this->button_edit->enabled = true;
                $this->button_add->enabled = false;

                if($this->tree->selectedItems[0]->value->additionally!='' and file_exists($this->tree->selectedItems[0]->value->additionally)){

                    $this->label_video->text = $this->tree->selectedItems[0]->value->name .(($this->tree->selectedItems[0]->parent->value->additionally!='')?' / '.$this->tree->selectedItems[0]->parent->value->additionally:'');


                    if($this->play_now != $this->tree->selectedItems[0]->value->additionally){


                        $keys = $this->tree->selectedItems[0]->value->id;

                        $name_index = $this->names_index[$keys[0]];

                        $this->play_now = $this->tree->selectedItems[0]->value->additionally;
                        $this->mediaView->stop();
                        $this->mediaView->open($this->tree->selectedItems[0]->value->additionally, true);
                        $this->mediaView->player->cycleCount = 5000;
                        $this->mediaView->player->volume = $this->slider_volume->value;

                        $this->button_stop->text = "Выключить";

                    }


                }

            }

        } else {
            $this->button_edit->enabled = false;
            $this->button_add->enabled = false;
        }
    }

    /**
     * @event button_edit.click-Left
     */
    function doButton_editClickLeft(UXMouseEvent $e = null)
    {
        $this->panel_edit->visible = true;
        $this->button_edit->visible = false;
        $this->tree->enabled = false;
        $this->button_add->enabled = false;
        $this->tabPane->enabled = false;
        $this->edit_search->enabled = false;
        $this->button_search->enabled = false;

        $this->edit_name->text = $this->tree->selectedItems[0]->value->name;
        $this->edit_additionally->text = $this->tree->selectedItems[0]->value->additionally;

        if($this->tree->selectedItems[0]->value->type == 'level_1'){

            $this->button_delete->visible = false;
            $this->edit_name->enabled = false;
            $this->edit_additionally->enabled = true;
            $this->edit_additionally->promptText = 'Деятельность';

        }
        elseif($this->tree->selectedItems[0]->value->type == 'level_2'){
            $this->button_delete->visible = true;
            $this->edit_name->enabled = true;
            $this->edit_additionally->enabled = false;
            $this->edit_additionally->promptText = 'Ссылка на файл';


            if($this->edit_additionally->text == '' or !file_exists($this->edit_additionally->text)){
                $this->edit_additionally->text = 'Выберите файл..';
                $this->edit_additionally->enabled = true;
            }

        }


    }

    /**
     * @event button_save.click-Left
     */
    function doButton_saveClickLeft(UXMouseEvent $e = null)
    {
        $this->panel_edit->visible = false;
        $this->button_edit->visible = true;
        $this->tree->enabled = true;
        $this->button_add->enabled = true;
        $this->tabPane->enabled = true;

        $this->edit_search->enabled = true;
        $this->button_search->enabled = true;

        if($this->tree->selectedItems[0]->value->type == 'level_1'){

            $additionally_old = $this->tree->selectedItems[0]->value->additionally;

            if($this->edit_additionally->text!=$additionally_old){
                $this->tree->selectedItems[0]->value = new ItemValue(
                    'level_1',
                    $this->tree->selectedItems[0]->value->id,
                    $this->tree->selectedItems[0]->value->name,
                    $this->tree->selectedItems[0]->value->name . (($this->edit_additionally->text!='')?' / '.$this->edit_additionally->text:''),
                    $this->edit_additionally->text
                );

                $keys = $this->tree->selectedItems[0]->value->id;

                $name_index = $this->names_index[$keys[0]];

                $this->context[$name_index]['items'][$keys[1]]['items'][(string)$keys[2]]['additionally'] = $this->edit_additionally->text;

                $this->save_change('Изменить', 'Деятельность', $keys, [
                    'additionally_new' => $this->edit_additionally->text,
                    'additionally_old' => $additionally_old,
                ]);
            }

        }
        elseif($this->tree->selectedItems[0]->value->type == 'level_2'){

            $name_old = $this->tree->selectedItems[0]->value->name;

            if($name_old!=$this->edit_name->text){
                $this->tree->selectedItems[0]->value = new ItemValue(
                    'level_2',
                    $this->tree->selectedItems[0]->value->id,
                    $this->edit_name->text,
                    $this->edit_name->text,
                    $this->tree->selectedItems[0]->value->additionally
                );

                $keys = $this->tree->selectedItems[0]->value->id;

                $name_index = $this->names_index[$keys[0]];

                foreach($this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'] as $key=>$value){
                    if($key == $keys[3]){
                        $value['name'] = $this->edit_name->text;
                        break;
                    }
                }

                $this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'][$keys[3]] = $value;

                $this->save_change( 'Изменить', 'Ритм', $keys, [
                    'name_new' => $this->edit_name->text,
                    'name_old' => $name_old,
                ]);
            }

        }

        $this->doTreeClickLeft();

    }

    /**
     * @event button_delete.click-Left
     */
    function doButton_deleteClickLeft(UXMouseEvent $e = null)
    {

        if($answer = UXDialog::showAndWait('Вы уверены что хотите удалить файл: '.$this->tree->selectedItems[0]->value->additionally .' ?')){

            if($answer == "O"){

                $this->form('MainForm')->showPreloader('Удаление файла');

                $this->panel_edit->visible = false;
                $this->button_edit->visible = true;
                $this->tree->enabled = true;
                $this->button_add->enabled = true;
                $this->tabPane->enabled = true;

                $this->edit_search->enabled = true;
                $this->button_search->enabled = true;

                if($this->tree->selectedItems[0]->value->type == 'level_2'){

                    if($this->play_now == $this->tree->selectedItems[0]->value->additionally){
                        $this->doMediaViewConstruct();
                    }

                    $name_old = $this->tree->selectedItems[0]->value->name;

                    $keys = $this->tree->selectedItems[0]->value->id;

                    $this->tree->selectedItems[0]->parent->children->remove($this->tree->selectedItems[0]);


                    $name_index = $this->names_index[$keys[0]];

                    $values = $this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'];

                    $this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'] = [];

                    $file_to_delete = '';

                    foreach($values as $key=>$value){
                        if($key == $keys[3]){
                            if($value['additionally']!=''){
                                if(!substr_count($value['additionally'],'http://') and !substr_count($value['additionally'],'https://') and file_exists($value['additionally'])){
                                    $file_to_delete = $value['additionally'];
                                }
                            }
                            continue;
                        }
                        $this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'][$key] = $value;
                    }

                    $this->save_change( 'Удалить', 'Ритм', $keys, [
                        'name_old' => $name_old,
                    ]);

                }

                $this->doTreeClickLeft();

                if($file_to_delete!=''){

                    $thread = new Thread(function ()use($file_to_delete){

                        for($i=1;$i<=300;$i++){
                            if(!file_exists($file_to_delete)){
                                break;
                            }
                            $result = unlink($file_to_delete);
                            if($result){
                                break;
                            }
                            sleep(1);
                        }

                        uiLater(function() use($file_to_delete){
                            $this->form('MainForm')->hidePreloader();
                            if(file_exists($file_to_delete)){
                                alert('Сбой удаления файла: '.$file_to_delete);
                            }

                        });
                    });
                    $thread->start();


                }



            }

        }

    }

    /**
     * @event button_stop.click-Left
     */
    function doButton_stopClickLeft(UXMouseEvent $e = null)
    {
        if($this->button_stop->text == 'Выключить'){
            $this->mediaView->player->pause();
            $this->button_stop->text = 'Включить';
        }
        else{
            $this->mediaView->player->play();
            $this->button_stop->text = 'Выключить';
        }
    }

    /**
     * переводит строку на латиницу
     */
    public function translate($string)
    {

        $arStrES = array("ае","уе","ое","ые","ие","эе","яе","юе","ёе","ее","ье","ъе","ый","ий");
        $arStrOS = array("аё","уё","оё","ыё","иё","эё","яё","юё","ёё","её","ьё","ъё","ый","ий");
        $arStrRS = array("а$","у$","о$","ы$","и$","э$","я$","ю$","ё$","е$","ь$","ъ$","@","@");

        $replace = array("А"=>"A","а"=>"a","Б"=>"B","б"=>"b","В"=>"V","в"=>"v","Г"=>"G","г"=>"g","Д"=>"D","д"=>"d",
            "Е"=>"Ye","е"=>"e","Ё"=>"Ye","ё"=>"e","Ж"=>"Zh","ж"=>"zh","З"=>"Z","з"=>"z","И"=>"I","и"=>"i",
            "Й"=>"Y","й"=>"y","К"=>"K","к"=>"k","Л"=>"L","л"=>"l","М"=>"M","м"=>"m","Н"=>"N","н"=>"n",
            "О"=>"O","о"=>"o","П"=>"P","п"=>"p","Р"=>"R","р"=>"r","С"=>"S","с"=>"s","Т"=>"T","т"=>"t",
            "У"=>"U","у"=>"u","Ф"=>"F","ф"=>"f","Х"=>"Kh","х"=>"kh","Ц"=>"Ts","ц"=>"ts","Ч"=>"Ch","ч"=>"ch",
            "Ш"=>"Sh","ш"=>"sh","Щ"=>"Shch","щ"=>"shch","Ъ"=>"","ъ"=>"","Ы"=>"Y","ы"=>"y","Ь"=>"","ь"=>"",
            "Э"=>"E","э"=>"e","Ю"=>"Yu","ю"=>"yu","Я"=>"Ya","я"=>"ya","@"=>"y","$"=>"ye");

        $string = str_replace($arStrES, $arStrRS, $string);
        $string = str_replace($arStrOS, $arStrRS, $string);

        /*$string = strtr($string,$replace);*/
        foreach($replace as $replace_k => $replace_v){
            $string = str_replace($replace_k, $replace_v, $string);
        }

        $string = preg_replace('%[^A-Za-zа-яА-Я0-9\- ]%', '', $string);
        $string = str_replace(['   ','  ',' '], '-', $string);

        return $string;

    }


    function file_upload($name, $name_index){
        $this->form('MainForm')->showPreloader('Выбор файла');

        if(!$this->fileChooser->execute()) {
            $this->form('MainForm')->hidePreloader();
            return false;
        }

        $this->form('MainForm')->showPreloader('Загрузка файла');

        /*сохранение видео*/
        $video_local = $this->fileChooser->file;

        $size = fs::size($video_local);
        $size_mb = round($size / 1024 / 1024);

        if($size_mb > 100){
            alert('Файл "'.$video_local.'" превышает ('.$size_mb.'мб) допустимый размер в 100мб!');
            $this->form('MainForm')->hidePreloader();
            return false;
        }

        $exp = explode(".", $video_local)[(count(explode(".", $video_local)) - 1)];
        $video_name = $this->translate($name);

        $file = $video_name.'.'.$exp;

        $video_url_local = './catalog/Video/'.$name_index.'/'.$file;

        foreach($this->names_index as $name_index_check){
            if(file_exists($video_url_local)){
                alert('Файл "'.$file.'" уже есть в папке '.$name_index_check);
                $this->form('MainForm')->hidePreloader();
                return false;
            }
        }

        copy($video_local,$video_url_local);

        $this->form('MainForm')->hidePreloader();

        return $video_url_local;
    }


    /**
     * @event edit_additionally.mouseDown-Left
     */
    function doEdit_additionallyMouseDownLeft(UXMouseEvent $e = null)
    {

        if($this->edit_additionally->text != 'Выберите файл..'){
            return;
        }

        $name = $this->edit_name->text;

        if($name == ''){
            alert('Введите название!');
            return;
        }

        $keys = $this->tree->selectedItems[0]->value->id;

        $name_index = $this->names_index[$keys[0]];

        /*сохранение видео*/
        $video_url_local = $this->file_upload($name, $name_index);

        if(!$video_url_local){
            return;
        }


        $this->tree->selectedItems[0]->value = new ItemValue(
            'level_2',
            $this->tree->selectedItems[0]->value->id,
            $this->tree->selectedItems[0]->value->name,
            $this->tree->selectedItems[0]->value->fullname,
            $video_url_local
        );

        foreach($this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'] as $key=>$value){
            if($key == $keys[3]){
                $value['additionally'] = $video_url_local;
                break;
            }
        }

        $this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'][$keys[3]] = $value;

        $this->save_change( 'Загрузить', 'Ритм', $keys, []);

        $this->edit_additionally->text = $video_url_local;
        $this->edit_additionally->enabled = false;
    }

    /**
     * @event button_add.click-Left
     */
    function doButton_addClickLeft($e = null)
    {

        $name = $this->edit_search->text;

        if($name == ''){
            alert('Введите название!');
            return;
        }

        $keys = $this->tree->selectedItems[0]->value->id;

        $name_index = $this->names_index[$keys[0]];

        /*сохранение видео*/
        $video_url_local = $this->file_upload($name, $name_index);

        if(!$video_url_local){
            return;
        }

        /*сохранение в каталог*/

        $key = 0;

        foreach($this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'] as $k=>$v){
            $k = (int)$k;
            if($k>$key){
                $key = $k;
            }
        }

        $key = $key + 1;
        $key = (string)$key;

        $this->context[$name_index]['items'][$keys[1]]['items'][$keys[2]]['items'][$key] = [
            'name' => $name,
            'additionally' => $video_url_local,
            'items' => false
        ];

        $this->save_change('Добавить', 'Ритм', $keys, [
            'name_new' => $name,
        ]);


        /*работа с листом*/
        $keys_parent_to_child = $keys;
        $keys_parent_to_child[] = $key;

        $item_name = new ItemValue('level_2', $keys_parent_to_child, $name, $name, $video_url_local);
        $item = new UXTreeItem($item_name);
        $item->graphic = new UXImageView(new UXImage('res://.data/img/live-streaming.png'));

        $this->tree->selectedItems[0]->children->add($item);


        $this->tree->selectedItems[0]->expanded = true;

        $this->edit_search->text ='';

    }

    /**
     * @event tabPane.construct
     */
    function doTabPaneConstruct(UXEvent $e = null)
    {



        foreach($this->arr_image_tab as $key=>$image){

            $this->tabPane->tabs[$key]->graphic = new UXImageView(new UXImage('res://.data/img/'.$image));
        }

    }

    /**
     * @event button_search.click-Left
     */
    function doButton_searchClickLeft(UXMouseEvent $e = null)
    {
        $searched = [];
        $search = $this->edit_search->text;

        if($search == ''){
            alert('Введите название!');
            return;
        }

        $name_index = $this->names_index[$this->tabPane->selectedIndex];

        foreach($this->context[$name_index]['items'] as $key_0=>$value_0){

            if($value_0['items']){
                foreach($value_0['items'] as $key_1=>$value_1){
                    if($value_1['items']){
                        foreach($value_1['items'] as $key_2=>$value_2){
                            $result = str::contains(str::lower($value_2['name']),str::lower($search));
                            if ($result != false){
                                $searched[] = [$this->tabPane->selectedIndex,$key_0,$key_1,$key_2];
                            }
                        }
                    }
                }
            }


        }


        if(count($searched)==0){
            alert('Нет записей по запросу: '.$search);
        }
        else{

            $this->tree->root = new UXTreeItem();
            $this->add_to_tree($this->tree->root, $this->context[$name_index]['items'], [$this->tabPane->selectedIndex], 0, $searched);
        }



    }


    /**
     * @event button_stop.mouseEnter
     */
    function doButton_stopMouseEnter(UXMouseEvent $e = null)
    {
        $this->button_stop->opacity = 1;
    }

    /**
     * @event button_stop.mouseExit
     */
    function doButton_stopMouseExit(UXMouseEvent $e = null)
    {
        $this->button_stop->opacity = 0.08;
    }

    /**
     * @event slider_volume.construct
     */
    function doSlider_volumeConstruct(UXEvent $e = null)
    {

        $this->slider_volume->value = $this->mediaView->player->volume;
    }

    /**
     * @event slider_volume.mouseDrag
     */
    function doSlider_volumeMouseDrag(UXMouseEvent $e = null)
    {
        $this->mediaView->player->volume = $this->slider_volume->value;

    }

    /**
     * @event slider_volume.mouseEnter
     */
    function doSlider_volumeMouseEnter(UXMouseEvent $e = null)
    {

        $this->slider_volume->opacity = 1;
    }

    /**
     * @event slider_volume.mouseExit
     */
    function doSlider_volumeMouseExit(UXMouseEvent $e = null)
    {

        $this->slider_volume->opacity = 0.08;
    }


    /**
     * @event list_changes.click-Left
     */
    function doList_changesClickLeft(UXMouseEvent $e = null)
    {
        if($this->list_changes->width == 528){
            $this->list_changes->width = 296;
        }
        else{
            $this->list_changes->width = 528;
        }
    }


    /**
     * @event edit_search.construct
     */
    function doEdit_searchConstruct(UXEvent $e = null)
    {
        $this->edit_search->on('keyUp', function(UXKeyEvent $e) {
            if($e->codeName == "Enter"){
                if(!isset($this->last_edit_search_on) or $this->last_edit_search_on !=$this->edit_search->text){

                    $this->last_edit_search_on = $this->edit_search->text;
                    $this->doButton_searchClickLeft();
                }
            }
        });
    }

    /**
     * @event edit_name.construct
     */
    function doEdit_nameConstruct(UXEvent $e = null)
    {
        $this->edit_name->on('keyUp', function(UXKeyEvent $e) {
            if($e->codeName == "Enter"){
                $this->doButton_saveClickLeft();
            }
        });
    }

    /**
     * @event edit_additionally.construct
     */
    function doEdit_additionallyConstruct(UXEvent $e = null)
    {
        $this->edit_additionally->on('keyUp', function(UXKeyEvent $e) {
            if($e->codeName == "Enter"){
                $this->doButton_saveClickLeft();
            }
        });
    }

    /**
     * @event close 
     */
    function doClose(UXWindowEvent $e = null)
    {    
        $this->thread->interrupt();
    }

    /**
     * @event slider_volume.mouseDown-Left 
     */
    function doSlider_volumeMouseDownLeft(UXMouseEvent $e = null)
    {    
        $this->mediaView->player->volume = $this->slider_volume->value;
    }

    
    function add_to_tree(UXTreeItem $item_parent, $values, $keys_parent, $level = 0, $searched = []){


        foreach($values as $key=>$value){

            $keys_parent_to_child = $keys_parent;
            $keys_parent_to_child[] = $key;

            $fullname = (($level == 1)?$value['name'] . (($value['additionally']!='')?' / '.$value['additionally']:'') :$value['name']);

            $item_name = new ItemValue('level_'.$level, $keys_parent_to_child, $value['name'], $fullname, $value['additionally']);
            $item = new UXTreeItem($item_name);

            if($level == 0 || $level == 1){
                $image = 'res://.data/img/'.$this->images_to_tree['level '.$level][$key];
            }
            else{
                $image = 'res://.data/img/live-streaming.png';
            }

            if(count($searched)>0){
                foreach($searched as $search_keys){
                    if($level == 0 and $search_keys[0] == $keys_parent_to_child[0] and $search_keys[1] == $keys_parent_to_child[1]){
                        $item_parent->expanded = true;
                    }
                    if($level == 1 and $search_keys[0] == $keys_parent_to_child[0] and $search_keys[1] == $keys_parent_to_child[1] and $search_keys[2] == $keys_parent_to_child[2]){
                        $item_parent->expanded = true;
                    }
                    if($level == 2 and $search_keys[0] == $keys_parent_to_child[0] and $search_keys[1] == $keys_parent_to_child[1] and $search_keys[2] == $keys_parent_to_child[2] and $search_keys[3] == $keys_parent_to_child[3]){
                        $item_parent->expanded = true;
                        $image = 'res://.data/img/video-search.png';
                    }
                }
            }

            $item->graphic = new UXImageView(new UXImage($image));


            if(is_array($value['items'])){
                $this->add_to_tree($item, $value['items'], $keys_parent_to_child, ($level + 1), $searched);
            }

            $item_parent->children->add($item);

        }


    }


}

class ItemValue
{
    public $id;
    public $text;
    public $name;
    public $fullname;
    public $additionally;

    public function __construct($type, $id, $name, $fullname, $additionally)
    {
        $this->id = $id;
        $this->type = $type;
        $this->name = $name;
        $this->fullname = $fullname;
        $this->additionally = $additionally;

    }

    public function __toString()
    {
        return $this->fullname;
    }
}
