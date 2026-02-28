<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title')</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
        
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            brand: {
                                primary: '#b20003',
                                shadow: 'rgba(178, 0, 3, 0.3)',
                            }
                        },
                        fontFamily: {
                            sans: ['Outfit', 'sans-serif'],
                        },
                    }
                }
            }
        </script>

        <style>
            body {
                background-color: #050505;
                color: white;
            }
            .glow-on-hover:hover {
                box-shadow: 0 0 20px rgba(178, 0, 3, 0.4);
            }
            .bg-glow {
                background: radial-gradient(circle at center, rgba(178, 0, 3, 0.08) 0%, rgba(0, 0, 0, 0) 70%);
            }
        </style>
    </head>
    <body class="antialiased font-sans overflow-hidden">
        <div class="relative flex items-top justify-center min-h-screen sm:items-center sm:pt-0 bg-glow">
            <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
                <div class="flex flex-col items-center pt-8 sm:justify-start sm:pt-0">
                    <div class="mb-8 relative">
                        <!-- Red Glow Spot -->
                        <div class="absolute -inset-4 bg-brand-primary/20 blur-2xl rounded-full"></div>
                        <h1 class="text-[120px] font-black tracking-tighter text-white relative leading-none select-none">
                            @yield('code')
                        </h1>
                    </div>

                    <div class="text-center px-4">
                        <h2 class="text-2xl font-bold text-white mb-4 uppercase tracking-widest">
                            @yield('message')
                        </h2>
                        <p class="text-zinc-500 mb-12 max-w-md mx-auto leading-relaxed">
                            @yield('description')
                        </p>

                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            @yield('actions')
                        </div>
                    </div>
                </div>
            </div>

            <!-- Decorative corner glows -->
            <div class="absolute top-0 left-0 w-64 h-64 bg-brand-primary/5 blur-[120px] -translate-x-1/2 -translate-y-1/2 rounded-full"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-brand-primary/5 blur-[120px] translate-x-1/4 translate-y-1/4 rounded-full"></div>
        </div>
    </body>
</html>
