import React, { useState } from 'react';
import { ChevronDown } from 'lucide-react';

const Hero: React.FC = () => {
  const handleScroll = (e: React.MouseEvent<HTMLAnchorElement>, href: string) => {
    e.preventDefault();
    const targetId = href.replace('#', '');
    const element = document.getElementById(targetId);

    if (element) {
      const offset = 100;
      const elementPosition = element.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.scrollY - offset;

      window.scrollTo({
        top: offsetPosition,
        behavior: "smooth"
      });
    }
  };

  return (
    <section id="hero" className="relative h-screen flex items-center justify-center overflow-hidden">
      {/* Background Image with Overlay */}
      <div className="absolute inset-0 z-0">
        <img 
          src="https://picsum.photos/seed/chinese-food-dark/1920/1080" 
          alt="Chinese Feast" 
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-black/50" />
      </div>

      {/* Content */}
      <div className="relative z-10 text-center px-4 max-w-4xl mx-auto">
        <span className="block text-secondary text-lg md:text-xl uppercase tracking-[0.2em] mb-4 animate-fade-in-up">
          Authentic Cantonese Cuisine
        </span>
        <h1 className="text-5xl md:text-7xl lg:text-8xl font-serif text-white font-bold mb-8 animate-fade-in-up animation-delay-200 leading-tight">
          Taste the <span className="text-secondary italic">Tradition</span>
        </h1>
        <p className="text-gray-200 text-lg md:text-xl mb-10 max-w-2xl mx-auto animate-fade-in-up animation-delay-400 font-light">
          Experience a culinary journey through the provinces of China in a modern, elegant setting.
        </p>
        <div className="flex flex-col md:flex-row gap-4 justify-center animate-fade-in-up animation-delay-600">
          <a 
            href="#menu" 
            onClick={(e) => handleScroll(e, '#menu')}
            className="px-8 py-4 bg-primary text-white font-bold uppercase tracking-widest hover:bg-red-900 transition-colors cursor-pointer"
          >
            View Menu
          </a>
          <a 
            href="#contact" 
            onClick={(e) => handleScroll(e, '#contact')}
            className="px-8 py-4 border-2 border-white text-white font-bold uppercase tracking-widest hover:bg-white hover:text-foreground transition-colors cursor-pointer"
          >
            Book a Table
          </a>
        </div>
      </div>

      {/* Scroll Indicator */}
      <a 
        href="#about" 
        onClick={(e) => handleScroll(e, '#about')}
        className="absolute bottom-8 left-1/2 transform -translate-x-1/2 text-white animate-bounce cursor-pointer z-10"
      >
        <ChevronDown className="w-8 h-8" />
      </a>
    </section>
  );
};

export default Hero;