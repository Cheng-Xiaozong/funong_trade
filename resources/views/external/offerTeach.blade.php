<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>报价指南</title>
    <style>
        *{
            margin: 0;
            padding: 0;
        }
        @media screen and (min-width: 320px){
            body{
                font-size:20px;
            }
            .title{
                height: 60px;
            }
            .title p{
                line-height: 60px;
            }
        }
        @media screen and (min-width: 600px){
            body{
                font-size:30px;
            }
            .title{
                height: 100px;
            }
            .title p{
                line-height: 100px;
            }
        }
        @media screen and (min-width: 800px){
            body{
                font-size:35px;
            }
            .title{
                height: 120px;
            }
            .title p{
                line-height: 120px;
            }
        }
        @media screen and (min-width: 1080px){
            body{
                font-size:40px;
            }
            .title{
                height: 150px;
            }
            .title p{
                line-height: 150px;
            }
        }
        .box{
            width: 100%;
            min-width:320px;
            margin:0 auto;
        }
        .box img{
            width: 95%;
            max-width:1080px;
        }
        .title{
            margin:0 auto;
            text-align:center;
            width: 100%;
            border-bottom:1px solid #ddd;
            max-width:1200px;
            /* font-size:40px; */
        }

        .content{
            text-align: center;
            padding:2.5%;
        }
    </style>
</head>
<body>
<div class="box">
    <div class="title">
        <p>新注册的卖家怎么报价?</p>
    </div>
    <div class="content">
        <div class="loading">
            <img src="{{asset('imgs/loading.png')}}" alt="">
        </div>
        <div>
            <img src="{{asset('imgs//1.png')}}" alt="">
        </div>
        <div>
            <img src="{{asset('imgs//2.png')}}" alt="">
        </div>
        <div>
            <img src="{{asset('imgs//3.png')}}" alt="">
        </div>
        <div>
            <img src="{{asset('imgs//4.png')}}" alt="">
        </div>
        <div>
            <img src="{{asset('imgs//5.png')}}" alt="">
        </div>
        <div>
            <img src="{{asset('imgs//6.png')}}" alt="">
        </div>
    </div>
</div>
</body>
</html>