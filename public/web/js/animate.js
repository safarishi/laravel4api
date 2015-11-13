'use strict';
indexApp.animation('.readIng', function ($window) {
    return{
        addClass: function(ele,done){
            alert('x');
            console.log(ele);
            //TweenMax.set(ele,{
            //    position: 'absolute',
            //    top: '100%'
            //});
            TweenMax.to(ele,1.5,{
                top: '100px'
            });
            $window.setTimeout(done,1500);
        },
        enter: function(ele,done){
            alert('x');
            console.log(ele);
            //TweenMax.set(ele,{
            //    position: 'absolute',
            //    top: '100%'
            //});
            TweenMax.to(ele,1.5,{
                top: '0'
            });
            $window.setTimeout(done,1500);
        },
        leave: function(ele){
            //TweenMax.set(ele,{
            //    position: 'absolute',
            //    top: '0'
            //});
            TweenMax.to(ele,1.5,{
                top: '100%'
            });
        }
    }
});