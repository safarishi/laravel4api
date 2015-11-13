'use strict';
var indexApp = angular.module('indexApp',['ionic','cookie','ngAnimate','httpSever','sever','LoadingApp',
    'jqLite','scrollResize','browser','arrayBox','popBox','NoSQL','angularFileUpload','loading',
    'Base64','url','angular-cache','ngIOS9UIWebViewPatch']);

//设置LRU算法，访问的页面写入内存，在60秒内重新访问之前页面无需耗费流量
indexApp.run(function ($http, CacheFactory) {
    $http.defaults.cache = CacheFactory('myCache', {
        capacity: 5, //最大缓存数量
        maxAge: 60 * 1000, //新插入的缓存过期时间
        cacheFlushInterval: 60 * 1000, //所有缓存过期时间
        deleteOnExpire: 'aggressive', //删除过期缓存
        storageMode: 'memory' //缓存写入内存
    });
});

var signIn = false;
var commentId;//回复id
var commentIndex;//评论或者回复
var search = false;
var comment = false;
var collectNews = false;

//主控制器
indexApp.controller('mainController',function($scope,$cacheFactory,$rootScope,urlBox,$location,loading,browser,promise,
                                              jQuery,scroll,cookie,popBox,NoSQL,$timeout,loadPop,arrayBox,CacheFactory){
    $scope.$on('$stateChangeSuccess',function(){
        if($location.$$path == '/index/mainText' || $location.$$path == '/index/viewComments'){
            $scope.footerShow = true;    //是否显示页脚
        }
        else{
            $scope.footerShow = false;    //是否显示页脚
        }
        if($location.$$path == '/index/thirdPartyLogin'){
            $scope.but2Show = false;
        }
        else{
            $scope.but2Show = true;
        }
        if($location.$$path == '/index/home'){
            if(urlBox.getUrl('token')){
                promise.getThirdToken(urlBox.getUrl('token')).success(function(data){
                    promise.accessToken(data).success(function(data){
                        cookie.setCookie('access_token',data.access_token, 's' + data.expires_in);
                        $timeout(function(){
                            popBox.showConfirm('提示','登录成功!');
                        },5000)
                    });
                });
            }
        }
    });
    //判断初始是否登录
    if(cookie.getCookie('access_token')){
        signIn = true;
    }
    else{
        signIn = false;
    }
    // 加载框
    if($location.$$path == '/index/home'){
        loading.hideLoading();
    }
    else{
        $scope.deleteLoading = false;
        loading.hideLoading();
    }
    //左侧滑动栏宽度
    $scope.sideWidh = browser.factory()[0] * 4 / 5;
    //页脚控制器
    var footerCommentShow = false;
    //footer变换
    $scope.footerCommentShow = footerCommentShow;
    //隐藏遮罩层
    $scope.footerMaskLayerShow = footerCommentShow;
    var close = function(){
        if($location.$$path == '/index/communityContent'){
            $scope.footerShow = false;
        }
        $scope.footerMaskLayerShow = false;
        $scope.footerCommentShow = false;
        $scope.footerStyle = {
            height: '44px'
        };
    };
    $scope.closeFooter = function(){
        var len = jQuery.$('commentText').length;
        for(var i = 0;i < len;i++){
            if(jQuery.$('commentText')[i].value){
                jQuery.$('commentText')[i].value = '';
            }
        }
        close();
    };
    //新闻详情内容
    $scope.$on('$stateChangeSuccess',function(){
        if($location.$$path == '/index/mainText'){
            promise.getArticles(cookie.getCookie('newsId')).success(function(data) {
                $scope.booksText = data.data;
                //增加分享
                mobShare.config({
                    appkey: 'ab27042c2b50', // appkey
                    params: {
                        url: location.href, // 分享链接
                        title: '中国港行发展评论', // 分享标题
                        description: '', // 分享内容
                        pic: data.data[0].url // 分享图片，使用逗号,隔开
                    }
                });
            }).error(function(){
                popBox.showConfirm('警告!','获取期刊失败!');
            });
        }
        //放大图片按钮
        $scope.enlarge = function(url){
            $scope.readIng = true;
            $scope.readIngUrl = url;
            //$animate['addClass'](angular.element(document.getElementsByClassName('reading')),'readIng');
        };
        //关闭期刊
        $scope.close = function(){
            $scope.readIng = false;
        };
    });
    //搜索新闻
    $scope.searchFor = function(id,OperType){
        comment = false;
        search = true;
        collectNews = false;
        loadPop.spinningBubbles();
        cookie.setCookie('newsId',id);
        if(OperType){
            cookie.setCookie('OperType',OperType);
        }
        else{
            cookie.setCookie('OperType','');
        }
        jQuery.$('backdrop').css({background:'black',opacity:'0.5'});
    };
    //新闻
    $scope.mainTextData = function(msg,OperType){
        comment = false;
        search = false;
        collectNews = false;
        cookie.setCookie('newsId',msg);
        if(OperType){
            cookie.setCookie('OperType',OperType);
        }
        else{
            cookie.setCookie('OperType','');
        }
        jQuery.$('backdrop').css({background:'black',opacity:'0.5'});
    };
    //用户评论
    $scope.userComment = function(msg){
        console.log(msg)
        comment = true;
        search = false;
        collectNews = false;
        cookie.setCookie('newsId',msg);
        jQuery.$('backdrop').css({background:'black',opacity:'0.5'});
    };
    $scope.popBox = function(index){
        //清理缓存
        if(index == 1 && $location.$$path == '/index/userSetUp'){
            //清除cookie
            CacheFactory.clearAll(); //清理http缓存
            cookie.delCookie('newsId');  //清除新闻cookie
            cookie.delCookie('img_token');  //清除图片的token
            cookie.delCookie('OperType');
            $timeout(function(){
                location.reload();
            })
        }
    };
    //本文评论
    var proThisData = function(){
        if($location.$$path == '/index/viewComments' && cookie.getCookie('newsId')) {
            loadPop.spinningBubbles();
            promise.getArticlesComments(cookie.getCookie('newsId')).success(function (data) {
                $scope.commentThisData = data.lists;
                scroll.resize();
            }).error(function (data) {
                popBox.showConfirm('警告!',data.error_description);
            });
        }
    };
    proThisData();
    //其他评论
    var proOthers = function(){
        if($location.$$path == '/index/viewComments' && cookie.getCookie('newsId')) {
            promise.getArticlesComments(cookie.getCookie('newsId')).success(function (data) {
                $scope.commentOthers = data.extras;
                loadPop.stopAll();
                scroll.resize();
            }).error(function (data) {
                popBox.showConfirm('警告!',data.error_description);
            });
        }
    };
    proOthers();
    $scope.$on('$stateChangeSuccess',function(){
        //收藏
        if(cookie.getCookie('access_token') && cookie.getCookie('newsId') && $location.$$path == '/index/mainText') {
            promise.getArticles(cookie.getCookie('newsId'), cookie.getCookie('access_token')).success(function (data) {
                //增加收藏
                if(data.is_starred){
                    jQuery.$('collectionbut').addClass('collectionbutAction');
                }
                else{
                    jQuery.$('collectionbut').removeClass('collectionbutAction');
                }
            })
        }
        proThisData();
    });
    $scope.$on('$stateChangeSuccess',function(){
        $scope.footerMaskLayerShow = false;
        proOthers();
    });
    $scope.shareShow = false; //是否显示分享
    //尾页评论框
    $scope.sendOutBox = function(index,id){
        $scope.footerShow = true;
        if(id){
            commentId = id;
        }
        $timeout(function(){
            if(jQuery.$('footer')[0].offsetHeight == 129 && commentIndex == 'commentReplies' && index == 'commentThis'){
                commentIndex = 'commentReplies'
            }
            else{
                commentIndex = index;
            }
            $scope.footerMaskLayerShow = true;
            $scope.footerCommentShow = true;
            $scope.footerStyle = {
                height: '129px'
            };
        })
    };
    //社区
    var slideDownIndex = true;
    var slideDownIndexBut = true;
    $scope.slideDownIndex = false;
    $scope.slideDownIndexBut = slideDownIndexBut;
    //热门标签
    promise.getType({type: 'hot'}).success(function(data){
        $scope.floatBoxs = data;
        $scope.heightFix = {};
    }).error(function(data){
        popBox.showConfirm('警告!',data.error_description);
    });
    //点击浮动按钮增加评论
    var commentsBox = [];
    var commentTitle = [];
    $scope.addComments = function(id,event){
        commentsBox.push(id);
        commentTitle.push(event.target.innerText);
        //写入title
        var writeTitle = function(){
            commentTitle = arrayBox.delSame(commentTitle);
            if(commentTitle.length == 1){
                jQuery.$('communityItemLeft')[1].innerText = commentTitle[0];
            }
            else if(commentTitle.length != 1 && commentTitle.length != 0){
                var dataMain = commentTitle[0];
                for(var i = 1;i < commentTitle.length;i++){
                    dataMain = dataMain + '+' +commentTitle[i];
                }
                jQuery.$('communityItemLeft')[1].innerText = dataMain;
            }
            else if(commentTitle.length == 0){
                jQuery.$('communityItemLeft')[1].innerText = '';
                $scope.slideDownIndex = false;
            }
        };
        //判断是否选择
        if(angular.element(event.target).hasClass('choice')){
            angular.element(event.target).removeClass('choice');
            for(var i = 0;i < commentsBox.length;i++){
                if(commentsBox[i] == id){
                    commentsBox.splice(i,1);
                }
            }
            if(commentsBox[0] == id){
                commentsBox.splice(0,1);
            }
            if(commentsBox[commentsBox.length - 1] == id){
                commentsBox.splice(commentsBox.length - 1,1);
            }
            for(var h = 0;h < commentTitle.length;h++){
                if(commentTitle[h] == event.target.innerText){
                    commentTitle.splice(h,1);
                }
            }
            if(commentTitle[0] == event.target.innerText){
                commentTitle.splice(0,1);
            }
            if(commentTitle[commentTitle.length - 1] == event.target.innerText){
                commentTitle.splice(commentTitle.length - 1,1);
            }
            writeTitle();
        }
        else{
            angular.element(event.target).addClass('choice');
            writeTitle();
        }
        if(commentsBox.length != 0){
            loadPop.spinningBubbles();
            promise.getTypeComments(commentsBox).success(function(data){
                if(data.length != 0 && slideDownIndex){
                    $scope.slideDownIndex = true;
                }
                $scope.comComments = data;
                $timeout(function(){
                    //修复滚动条高度
                    $scope.heightFix = {
                        height: (
                        jQuery.$('list')[1].scrollHeight +
                        jQuery.$('commLineHeight')[0].children[0].scrollHeight +
                        jQuery.$('commLineHeight')[1].children[0].scrollHeight)  + 'px'
                    };

                    loadPop.stopAll();
                    scroll.resize();
                });
            }).error(function(data){
                popBox.showConfirm('警告!',data.error_description);
                loadPop.stopAll();
            });
        }
    };
    $scope.tabSlideDown = function(){
        //所有标签
        if(slideDownIndex){
            jQuery.$('communityItemLeft')[0].innerText = '所有标签';
            promise.getType({type: 'all'}).success(function(data){
                $scope.floatBoxs = data;
                slideDownIndex = !slideDownIndex;
                slideDownIndexBut = !slideDownIndexBut;
                $scope.slideDownIndex = slideDownIndex;
                $scope.slideDownIndexBut = slideDownIndexBut;
                $timeout(function(){
                    for(var i = 0;i < commentsBox.length;i++){
                        for(var k = 0;k < jQuery.$('float').length;k++){
                            if(jQuery.$('float')[k].attributes[3].value == commentsBox[i]){
                                jQuery.$('float').eq(k).addClass('choice');
                            }
                        }
                    }
                    scroll.resize();
                });
                scroll.resize();
            }).error(function(data){
                popBox.showConfirm('警告!',data.error_description);
            });
        }
        //热门标签
        else{
            jQuery.$('communityItemLeft')[0].innerText = '热门标签';
            promise.getType({type: 'hot'}).success(function(data){
                $scope.floatBoxs = data;
                slideDownIndex = !slideDownIndex;
                slideDownIndexBut = !slideDownIndexBut;
                $scope.slideDownIndex = slideDownIndex;
                $scope.slideDownIndexBut = slideDownIndexBut;
                if(jQuery.$('communityItemLeft')[1].innerText == ''){
                    $scope.slideDownIndex = false;
                }
                $timeout(function(){
                    for(var i = 0;i < commentsBox.length;i++){
                        for(var k = 0;k < jQuery.$('float').length;k++){
                            if(jQuery.$('float')[k].attributes[3].value == commentsBox[i]){
                                jQuery.$('float').eq(k).addClass('choice');
                            }
                        }
                    }
                    scroll.resize();
                });
                scroll.resize();
            }).error(function(data){
                popBox.showConfirm('警告!',data.error_description);
            });
        }
    };
    //发表评论
    $scope.sendOut = function(){
        //评论本文
        if(commentIndex && commentIndex == 'commentThis'){
            if(cookie.getCookie('access_token') && cookie.getCookie('newsId')){
                loadPop.spinningBubbles();
                promise.postArticlesComments(cookie.getCookie('newsId'),{content: jQuery.$('commentTextArea')[0].value},cookie.getCookie('access_token'))
                    .success(function(){
                        if($location.$$path == '/index/viewComments' || '/index/mainText'){
                            promise.getArticlesComments(cookie.getCookie('newsId')).success(function(data){
                                if($location.$$path == '/index/mainText'){
                                    promise.getArticles('',cookie.getCookie('newsId')).success(function(data){
                                        $scope.comments = data.hot_comments;
                                    });
                                }
                                $scope.commentThisData = data.lists;
                                $scope.commentOthers = data.extras;
                                scroll.resize();
                                var len = jQuery.$('commentText').length;
                                for(var i = 0;i < len;i++){
                                    if(jQuery.$('commentText')[i].value){
                                        jQuery.$('commentText')[i].value = '';
                                    }
                                }
                                close();
                                loadPop.stopAll();
                            }).error(function(data){
                                loadPop.stopAll();
                                popBox.showConfirm('警告!',data.error_description);
                            });
                        }
                    })
                    .error(function(data){
                        loadPop.stopAll();
                        popBox.showConfirm('警告!',data.error_description);
                    });
            }
            else{
                popBox.showPopup($scope,'验证消息','请输入验证码');
            }
        }
        //回复本文评论
        else if(commentIndex && commentIndex == 'commentReplies' && typeof commentId =='number'){
            if(cookie.getCookie('access_token') && cookie.getCookie('newsId')){
                loadPop.spinningBubbles();
                promise.postArticlesReplies(commentId,{content: jQuery.$('commentTextArea')[0].value},cookie.getCookie('access_token'))
                    .success(function(){
                        if($location.$$path == '/index/viewComments' || $location.$$path == '/index/mainText'){
                            promise.getArticlesComments(cookie.getCookie('newsId')).success(function(data){
                                if($location.$$path == '/index/mainText'){
                                    promise.getArticles('',cookie.getCookie('newsId')).success(function(data){
                                        $scope.comments = data.hot_comments;
                                    });
                                }
                                $scope.commentThisData = data.lists;
                                $scope.commentOthers = data.extras;
                                scroll.resize();
                                var len = jQuery.$('commentText').length;
                                for(var i = 0;i < len;i++){
                                    if(jQuery.$('commentText')[i].value){
                                        jQuery.$('commentText')[i].value = '';
                                    }
                                }
                                close();
                                loadPop.stopAll();
                            }).error(function(data){
                                loadPop.stopAll();
                                popBox.showConfirm('警告!',data.error_description);
                            });
                        }
                        else if($location.$$path == '/index/communityContent'){
                            $scope.footerShow = false;    //是否显示页脚
                            $scope.footerMaskLayerShow = false;
                            if(comments.length != 0){
                                loadPop.spinningBubbles();
                                promise.getTypeComments(comments).success(function(data){
                                    if(data.length != 0 && slideDownIndex){
                                        $scope.slideDownIndex = true;
                                    }
                                    $scope.comComments = data;
                                    $timeout(function(){
                                        //修复滚动条高度
                                        $scope.heightFix = {
                                            height: (
                                            jQuery.$('list')[1].scrollHeight +
                                            jQuery.$('commLineHeight')[0].children[0].scrollHeight +
                                            jQuery.$('commLineHeight')[1].children[0].scrollHeight)  + 'px'
                                        };
                                        loadPop.stopAll();
                                        scroll.resize();
                                    });
                                }).error(function(data){
                                    popBox.showConfirm('警告!',data.error_description);
                                    loadPop.stopAll();
                                });
                            }
                        }
                    })
                    .error(function(data){
                        loadPop.stopAll();
                        popBox.showConfirm('警告!',data.error_description);
                    });
            }
            else{
                popBox.showPopup($scope,'验证消息','请输入验证码');
            }
        }
    };
    $scope.closeShare = function(){
        jQuery.$('sharebut').removeClass('shareReady');
        $scope.footerMaskLayerShow = false;
        $scope.shareShow = false;
    };
    $scope.textNews = function(id){
        cookie.setCookie('newsId',id);
        if ($location.$$path == '/index/mainText' && cookie.getCookie('newsId') != 0 && cookie.getCookie('newsId')){
            promise.getArticles('',cookie.getCookie('newsId')).success(function(data){
                //详情文章的内容
                cookie.setCookie('newsId',data.article.Id);
                var textData = data.article;
                $scope.textTitle = textData.Title;
                $scope.textOrigin = textData.Source;
                $scope.textAuthor = textData.Author;
                $scope.textCreated = textData.PublishUtcDate;
                $scope.textThumbnail = textData.PicName;
                jQuery.$('textContentMain')[0].innerHTML = '';
                jQuery.$('textContentMain').append(textData.Body);
                //聊天内容
                $scope.comments = data.hot_comments;
                //相关文章
                $scope.newsMain = data.related_articles;
                //重载滚动条
                scroll.resize();
            });
        }
    };
});

//页脚控制器
indexApp.controller('footerController',function($scope,$rootScope,promise,cookie,jQuery,popBox){
    //收藏
    $scope.collect = function(){
        if(cookie.getCookie('access_token') && cookie.getCookie('newsId')){
            if(jQuery.$('collectionbut').hasClass('collectionbutAction')){
                promise.deleteArticlesStars(cookie.getCookie('newsId'),cookie.getCookie('access_token')).success(function(){
                    jQuery.$('collectionbut').removeClass('collectionbutAction');
                }).error(function(){
                    popBox.showConfirm('警告!','取消收藏失败!');
                });
            }
            else{
                promise.putArticlesStars(cookie.getCookie('newsId'),cookie.getCookie('access_token')).success(function(){
                    jQuery.$('collectionbut').addClass('collectionbutAction');
                }).error(function(){
                    popBox.showConfirm('警告!','收藏失败!');
                });
            }
        }
        else{
            popBox.showConfirm('警告!','请先登录!');
        }
    };
});

//验证码弹出框控制器
indexApp.controller('popImg',function($scope,promise,cookie){
    promise.generateToken().success(function(data){
        $scope.imagesGet = '/generate_captcha?token=' + data;
        cookie.setCookie('img_token',data);
    });
    $scope.changeImg = function(){
        promise.generateToken().success(function(data){
            $scope.imagesGet = '/generate_captcha?token=' + data;
            cookie.setCookie('img_token',data);
        });
    };
});

//home首页
indexApp.controller('homeController',function($scope,promise,cookie,jQuery,$timeout){
    //首页书架
    promise.getArticles().success(function(data){
        var len = data.length;
        var mod = len % 2;
        var arry = [];
        var h = 0;
        for(var i = 0;i < (len - mod) / 2;i++){
            var arryChild = [];
            arryChild.push(data[h]);
            arryChild.push(data[h+1]);
            arry.push(arryChild);
            h+=2;
        }
        for(var i = 0;i < mod;i++){
            var arryChild = [
                data[len - mod + i]
            ];
            arry.push(arryChild);
        }
        $scope.books = arry;
        var k = setInterval(function(){
            if(jQuery.$('imgBook').length != 0 && jQuery.$('imgBook')[0].height != 0){
                $scope.shelfTop = {
                    top: (jQuery.$('imgBook')[0].height + jQuery.$('bookShelfHeight')[0].height*0.1 - jQuery.$('bottomShelf')[0].height / 3 + 5) + 'px'
                };
                $scope.$apply();
                clearInterval(k);
            }
        });
    });
    //点击期刊
    $scope.newsId = function(id){
        cookie.setCookie('newsId',id);
    };
});

//loadIn用户页面
indexApp.controller('userBox',function($scope,$rootScope,cookie,promise,NoSQL,jQuery,browser){
    //查询新消息
    $scope.$on('redPoint',function(ele,data){
        if(data.bool){
            $scope.butNames = [
                {
                    sref: 'main.userComment',
                    name: '我的评论', //用户界面未登录时候按钮名字
                    point: false     // 是否显示红点
                },
                {
                    sref: 'main.userCollection',
                    name: '我的收藏',
                    point: false
                },
                {
                    sref: 'main.userInformation',
                    name: '我的消息',
                    point: true
                },
                {
                    sref: 'main.userSetUp',
                    name: '设置',
                    point: false
                }
            ];
        }
        else{
            $scope.butNames = [
                {
                    sref: 'main.userComment',
                    name: '我的评论', //用户界面未登录时候按钮名字
                    point: false     // 是否显示红点
                },
                {
                    sref: 'main.userCollection',
                    name: '我的收藏',
                    point: false
                },
                {
                    sref: 'main.userInformation',
                    name: '我的消息',
                    point: false
                },
                {
                    sref: 'main.userSetUp',
                    name: '设置',
                    point: false
                }
            ];
        }
    });

    $scope.$on('$stateChangeSuccess',function(){
        $rootScope.slideMenu = false;
    });
    $rootScope.slideHide = function(){
        var transData = jQuery.$('menu-content')[0].style.cssText;
        var reg = /\-?[0-9]+\.?[0-9]*/g;
        var data = transData.match(reg);
        if(data[1] == 0 || (data[1] >= browser.factory()[0] * 4 / 5 - 1 && data[1] <= browser.factory()[0] * 4 / 5 + 1)){
            $rootScope.slideMenu = false;
        }
    };
    $rootScope.slideMenuHide = function(){
        //获取侧滑栏的css3的translate3d位置,拖拽小于160隐藏遮罩层
        var transData = jQuery.$('menu-content')[0].style.cssText;
        var reg = /\-?[0-9]+\.?[0-9]*/g;
        var data = transData.match(reg);
        if(data[1] <= browser.factory()[0] * 2 / 5 ){
            $rootScope.slideMenu = false;
        }
    };
    $rootScope.openSlide = function(){
        $rootScope.slideMenu = true;
        if(cookie.getCookie('access_token') == null || typeof cookie.getCookie('access_token') == 'undefined'){
            $scope.userHeadPic = '../web/mycompass/images/userOriginHead.png';  //用户头像
            $scope.userName = '用户登录'; //用户登录
            $scope.signOutBut = false;
            $scope.userHead = false;
        }
        else{
            promise.redPoint().success(function(data){
                $scope.$emit('redPoint',{bool: data.new_information});
            });
            promise.getUsers(cookie.getCookie('access_token')).success(function(data){
                if(data.avatar_url){
                    $scope.userHeadPic = data.avatar_url + '?' + Math.random();  //用户头像
                }
                else{
                    $scope.userHeadPic = '../web/mycompass/images/userOriginHead.png';  //用户默认头像
                }
                if(data.name){

                }
                else{
                    data.name = '点击编辑姓名';
                }
                $scope.userName = data.name;        //登录名称
                $scope.signOutBut = true;
                $scope.userHead = false;
            });
        }
        //退出
        $scope.signOut = function(){
            promise.invalidateToken(cookie.getCookie('access_token')).success(function(){
                cookie.delCookie('access_token');
                signIn = false;
            });
        };
    };
});

//用户信息界面
indexApp.controller('userData',function($scope,promise,cookie,$upload,loadPop,$location){
    var upDate = function(){
        if($location.$$path == '/index/signIn'){
            loadPop.spinningBubbles();
        }
        promise.getUsers(cookie.getCookie('access_token')).success(function(data){
            $scope.userHead = true;
            if(data.avatar_url){
                $scope.userDataImg = data.avatar_url + '?' +Math.random();
            }
            else{
                $scope.userDataImg = '../web/mycompass/images/userOriginHead.png';  //用户默认头像
            }
            $scope.butNames = [
                {
                    name : '邮箱',
                    data: data.email,
                    sref:'main.userReviseMail'
                },
                {
                    name: '姓名',
                    data: data.display_name,
                    sref:'main.userReviseUserName'
                },
                {
                    name: '地址',
                    data: data.address,
                    sref:'main.userReviseJob'
                }
            ];
            loadPop.stopAll();
        });
    };
    if(cookie.getCookie('access_token')){
        upDate();
    }
    $scope.$on('$stateChangeSuccess',function(){
        if(cookie.getCookie('access_token')){
            upDate();
        }
    });
    //上传头像
    $scope.onFileSelect = function(files){
        loadPop.spinningBubbles();
        for (var i = 0; i < files.length; i++) {
            $upload.upload({
                url: '/v3/user',
                method: 'POST',
                file: files[i],
                fileName: files[i].name,
                fileFormDataName: "avatar_url",
                headers: {
                    'Authorization' : cookie.getCookie('access_token')
                },
                formDataAppender: function (formData, key, value) {
                    formData.append("filename", files[i].name);
                }
            }).progress(function(evt) {

            }).success(function(data, status, headers, config) {
                $scope.userDataImg = data.avatar_url + '?' +Math.random();
                loadPop.stopAll();
            });
        }
    };
});

//登录注册页面
indexApp.controller('signInContent',function($scope,$rootScope,promise,$location,cookie,NoSQL,jQuery,popBox,browser){
    $scope.userText = {
        'height': browser.factory()[5] * 0.098 + 'px'
    };
    var check = false;
    var thirdLoginShow = true;
    $rootScope.thirdLoginShow = thirdLoginShow;
    $scope.but1 = '登录';
    $scope.but2 = '注册';
    $scope.text1 = '   请输入用户名';
    $scope.text3 = '   请输入邮箱地址';
    $scope.text2 = '   请输入密码';
    $scope.register = function(event){
        $scope.user = '';
        thirdLoginShow = !thirdLoginShow;
        $rootScope.thirdLoginShow = thirdLoginShow;
        if(event.srcElement.innerText == '注册'){
            check = false;
            $rootScope.title = '注册';
            $rootScope.headerTitle = '注册';
            $scope.text1 = '   请输入用户名';
            $scope.text3 = '   请输入邮箱地址';
            $scope.text2 = '   请输入密码';
            $scope.but1 = '注册';
            $scope.but2 = '登录';
            $scope.myTop = {top: 0};
        }
        else{
            check = false;
            $rootScope.title = '登录';
            $rootScope.headerTitle = '登录';
            $scope.text1 = '   请输入用户名';
            $scope.text2 = '   请输入密码';
            $scope.but1 = '登录';
            $scope.but2 = '注册';
        }
    };
    $scope.checkTouch = function(){
        check =! check;
    };
    $scope.land = function(event){
        if(event.srcElement.innerText == '注册'){
            if(check){
                promise.postUsers($scope.user).success(function(){
                    promise.accessToken({
                        username: $scope.user.email,
                        password: $scope.user.password
                    }).success(function(data){
                        cookie.setCookie('access_token',data.access_token, 's' + data.expires_in);
                        popBox.showConfirm('提示','注册成功!');
                        $location.path('/index/home');
                        signIn = false;
                        check = false;
                        $scope.user = '';
                    });

                }).error(function(data){
                    popBox.showConfirm('警告!',data.error_description);
                });
            }
            else{
                popBox.showConfirm('提示','请先同意本站条款!');
            }
        }
        else if(event.srcElement.innerText == '登录'){
            promise.accessToken($scope.user).success(function(data){
                cookie.setCookie('access_token',data.access_token, 's' + data.expires_in);
                popBox.showConfirm('提示','登录成功!');
                $scope.user = '';
                signIn = false;
                //个人消息写入数据库
                promise.getUserInformations(cookie.getCookie('access_token')).success(function(data){
                    var base = [];
                    for(var i = 0;i < data.length;i++){
                        base.push(data[i].id);
                    }
                    base = [
                        {
                            cid: -2,
                            userInformation: base
                        }
                    ];
                });
                $location.path('/index/home');
            }).error(function(data){
                popBox.showConfirm('警告!',data.error_description);
            });
        }
    };
});

//第三方页面控制器
indexApp.controller('userThirdBox',function($scope,promise){
    $scope.thirdBoxes = [
        {
            img: 'mycompass/images/weixing.png',
            content: '微信登录',
            href: ''
        },
        {
            img: 'mycompass/images/weibo.png',
            content: '微博登录',
            href: ''
        },
        {
            img: 'mycompass/images/qq.png',
            content: 'QQ登录',
            href: ''
        }
    ];
    promise.thirdPartyLogin('weibo').success(function(weiBo){
        $scope.thirdBoxes[1].href = weiBo;
    }).then(function(){
        promise.thirdPartyLogin('qq').success(function(qq){
            $scope.thirdBoxes[2].href = qq;
        }).then(function(){
            promise.thirdPartyLogin('weixin').success(function(weixin){
                $scope.thirdBoxes[0].href = weixin;
            });
        });
    });
});

//用户收藏页面控制器
indexApp.controller('userCollectionController',function($scope,$rootScope,promise,cookie,$timeout,$location,popBox,loadPop,jQuery){
    //相关文章
    var fun = function(){
        if($location.$$path == '/index/userCollection'){
            loadPop.spinningBubbles();
        }
        promise.getUserStars(cookie.getCookie('access_token')).success(function(data){
            $scope.news = data;
            loadPop.stopAll();
        }).error(function(data){
            popBox.showConfirm('警告!',data.error_description);
            loadPop.stopAll();
        });
    };
    $scope.collectNews = function(id){
        cookie.setCookie('newsId',id);
        comment = false;
        search = false;
        collectNews = true;
        jQuery.$('backdrop').css({background:'black',opacity:'0.5'});
    };
    $scope.$on('$stateChangeSuccess',function(){
        if(cookie.getCookie('access_token')){
            fun();
        }
        else{
            $timeout(function(){
                $location.path('/index/signIn');
            });
        }
    });
});

//用户设置控制器
indexApp.controller('userSetUp',function($scope){
    $scope.butNames = [
        {
            sref: 'main.aboutUs',
            name: '关于我们'
        },
        {
            sref: 'main.home',
            name: '清理缓存'
        }
    ];
});

//用户消息控制器
indexApp.controller('userInformationController',function($scope,$rootScope,promise,cookie,$location,$timeout,popBox,loadPop){
    $scope.$on('$stateChangeSuccess',function(){
        if(cookie.getCookie('access_token')){
            promise.deleteRedPoint();
            fun();
        }
        else{
            $timeout(function(){
                $location.path('/index/signIn');
            });
        }
    });
    var fun = function(){
        if($location.$$path == '/index/userInformation'){
            loadPop.spinningBubbles();
        }
        promise.getUserInformations(cookie.getCookie('access_token')).success(function(data){
            $scope.comments = data;
            loadPop.stopAll();
            var base = [];
            for(var i = 0;i < data.length;i++){
                base.push(data[i].id);
            }
            base = [
                {
                    cid: -2,
                    userInformation: base
                }
            ];
        }).error(function(data){
            popBox.showConfirm('警告!',data.error_description);
            loadPop.stopAll();
        });
    }
});

//我的评论页面控制器
indexApp.controller('userCommentController',function($scope,promise,cookie,$timeout,$location,popBox,loadPop){
    var fun = function(){
        if($location.$$path == '/index/userComment'){
            loadPop.spinningBubbles();
        }
        promise.getUserComments(cookie.getCookie('access_token')).success(function(data){
            $scope.comments = data;
            loadPop.stopAll();

        }).error(function(data){
            popBox.showConfirm('警告!',data.error_description);
            loadPop.stopAll();
        });
    };
    $scope.$on('$stateChangeSuccess',function(){
        if(cookie.getCookie('access_token')){
            fun();
        }
        else{
            $timeout(function(){
                $location.path('/index/signIn');
            });
        }
    });
});

//本文评论控制器
indexApp.controller('thisWriting',function($scope){
    $scope.origin = false;
});

//其他评论控制器
indexApp.controller('otherWriting',function($scope){
    $scope.origin = true;
});


//点赞控制器
indexApp.controller('favours',function($scope,promise,cookie,popBox){
    $scope.favours = function(articleId,commentId,event){
        if(cookie.getCookie('access_token')){
            if(!articleId){
                articleId = 1;
            }
            promise.putFavours('PUT',articleId,commentId,cookie.getCookie('access_token'))
                .success(function(){
                    if(event.srcElement.nextElementSibling){
                        event.srcElement.nextElementSibling.innerText = parseInt(event.srcElement.nextElementSibling.innerText) + 1;
                    }
                    else{
                        event.srcElement.innerText = parseInt(event.srcElement.innerText) + 1;
                    }
                }).error(function(data){
                    if(data.error_description == '您已点赞！'){
                        promise.putFavours('DELETE',articleId,commentId,cookie.getCookie('access_token'))
                            .success(function(){
                                if(event.srcElement.nextElementSibling){
                                    event.srcElement.nextElementSibling.innerText = parseInt(event.srcElement.nextElementSibling.innerText) - 1;
                                }
                                else{
                                    event.srcElement.innerText = parseInt(event.srcElement.innerText) - 1;
                                }
                            }).error(function(data){
                                popBox.showConfirm('警告!',data.error_description);
                            });
                    }
                    else{
                        popBox.showConfirm('警告!',data.error_description);
                    }
                });
        }
        else{
            popBox.showConfirm('警告!','请先登录!');
        }
    };
});

//修改姓名控制器
indexApp.controller('userNameRevise',function($scope,$rootScope,promise,cookie,$location,popBox){
    promise.getUsers(cookie.getCookie('access_token')).success(function(data){
        $scope.nameData = data.name;
    }).error(function(data){
        popBox.showConfirm('警告!',data.error_description);
    });
    $rootScope.revise = function(){
        if($location.$$path == '/index/userReviseUserName') {
            promise.postUserRevise({
                name: $scope.nameData
            },cookie.getCookie('access_token')).success(function () {
                $location.path('/index/signIn');
            }).error(function (data) {
                popBox.showConfirm('警告!',data.error_description);
            });
        }
    }
});

//修改邮箱控制器
indexApp.controller('userMailRevise',function($scope,$rootScope,promise,cookie,$location,popBox){
    promise.getUsers(cookie.getCookie('access_token')).success(function(data){
        $scope.mailData = data.UserEmail;
    }).error(function(data){
        popBox.showConfirm('警告!',data.error_description);
    });
    $rootScope.revise = function(){
        if($location.$$path == '/index/userReviseMail') {
            promise.postUserRevise({
                email: $scope.mailData
            },cookie.getCookie('access_token')).success(function () {
                $location.path('/index/signIn');
            }).error(function (data) {
                popBox.showConfirm('警告!',data.error_description);
            });
        }
    }
});

//修改地址控制器
indexApp.controller('userJobRevise',function($scope,$rootScope,promise,cookie,$location,popBox){
    promise.getUsers(cookie.getCookie('access_token')).success(function(data){
        $scope.jobData = data.Post;
    }).error(function(data){
        popBox.showConfirm('警告!',data.error_description);
    });
    $rootScope.revise = function(){
        if($location.$$path == '/index/userReviseJob'){
            promise.postUserRevise({address: $scope.jobData},
                cookie.getCookie('access_token')).success(function(data){
                $location.path('/index/signIn');
            }).error(function(data){
                    popBox.showConfirm('警告!',data.error_description);
            });
        }
    }
});


//第三方登录控制器
indexApp.controller('thirdPartyLogin',function($scope,browser,jQuery,urlBox,promise,$location,popBox,cookie){
    var urlData = urlBox.getUrl('name');
    if(urlData){
        $scope.user = {
            username: urlData
        }
    }
    else{
        $scope.user = {
            username: ''
        }
    }
    $scope.thirdLoginShow = false;
    $scope.but1 = '登录';
    $scope.userText = {
        'height': browser.factory()[5] * 0.098 + 'px'
    };
    jQuery.$('forget').addClass('displayNone');
    $scope.text1 = '   请输入用户名';
    $scope.text3 = '   请输入邮箱地址';
    $scope.text2 = '   请输入密码';
    var check = false;
    $scope.checkTouch = function(){
        check =! check;
    };
    $scope.land = function(){
        if(urlBox.getUrl('token')){
            var url = '?avatar_url=' + urlBox.getUrl('avatar_url') + '&token=' + urlBox.getUrl('token');
            if(check){
                promise.thirdPostUsers($scope.user,url).success(function(){
                    promise.thirdAccessToken(
                        {
                            username: $scope.user.email,
                            password: $scope.user.password
                        },url

                    ).success(function(data){
                            cookie.setCookie('access_token',data.access_token, 's' + data.expires_in);
                            $location.path('/index/home');
                            signIn = false;
                            check = false;
                            $scope.user = '';
                        });

                }).error(function(data){
                    popBox.showConfirm('警告!',data.error_description);
                });
            }
            else{
                popBox.showConfirm('提示','请先同意本站条款!');
            }
        }
        else{
            popBox.showConfirm('提示','非法操作!');
        }

    };
});


//关于我们控制器
indexApp.controller('aboutUs',function($scope,popBox){
    $scope.update = function(){
        popBox.showConfirm('提示','此版本已为最新版本!');
    };
});
