import React, { useState, useEffect, useCallback } from 'react';
import { X, ChevronLeft, ChevronRight, ZoomIn } from 'lucide-react';

const Gallery: React.FC = () => {
  const [selectedIndex, setSelectedIndex] = useState<number | null>(null);

  // Use higher resolution for better lightbox experience
  const images = [
    "https://picsum.photos/seed/dumpling1/800/800",
    "https://picsum.photos/seed/noodles2/800/800",
    "https://picsum.photos/seed/tea3/800/800",
    "https://picsum.photos/seed/duck4/800/800",
    "https://picsum.photos/seed/rice5/800/800",
    "https://picsum.photos/seed/chef6/800/800",
  ];

  const openLightbox = (index: number) => {
    setSelectedIndex(index);
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
  };

  const closeLightbox = useCallback(() => {
    setSelectedIndex(null);
    document.body.style.overflow = 'unset';
  }, []);

  const showNext = useCallback((e?: React.MouseEvent) => {
    e?.stopPropagation();
    setSelectedIndex((prev) => (prev === null ? null : (prev + 1) % images.length));
  }, [images.length]);

  const showPrev = useCallback((e?: React.MouseEvent) => {
    e?.stopPropagation();
    setSelectedIndex((prev) => (prev === null ? null : (prev - 1 + images.length) % images.length));
  }, [images.length]);

  // Keyboard navigation
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (selectedIndex === null) return;
      
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowRight') showNext();
      if (e.key === 'ArrowLeft') showPrev();
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [selectedIndex, closeLightbox, showNext, showPrev]);

  return (
    <section id="gallery" className="py-20 bg-background">
      <div className="container mx-auto px-4">
        <div className="text-center mb-16">
          <span className="text-primary font-bold uppercase tracking-widest text-sm">Visual Feast</span>
          <h2 className="text-4xl md:text-5xl font-serif font-bold text-foreground mt-2">Gallery</h2>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {images.map((src, index) => (
            <div 
              key={index} 
              onClick={() => openLightbox(index)}
              className="relative group overflow-hidden aspect-square rounded-lg cursor-pointer shadow-md hover:shadow-xl transition-all duration-300"
            >
              <img 
                src={src} 
                alt={`Gallery ${index + 1}`} 
                className="w-full h-full object-cover transform transition-transform duration-700 group-hover:scale-110"
                loading="lazy"
              />
              <div className="absolute inset-0 bg-foreground/0 group-hover:bg-foreground/40 transition-colors duration-300 flex items-center justify-center">
                 <div className="opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-4 group-hover:translate-y-0 flex flex-col items-center gap-2">
                    <ZoomIn className="text-white w-8 h-8" />
                    <span className="text-white uppercase tracking-widest font-bold text-sm border-b-2 border-secondary pb-1">View</span>
                 </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Lightbox Modal */}
      {selectedIndex !== null && (
        <div 
          className="fixed inset-0 z-[70] bg-black/95 flex items-center justify-center backdrop-blur-md animate-fade-in-up"
          onClick={closeLightbox}
        >
          <button 
            onClick={closeLightbox}
            className="absolute top-4 right-4 md:top-8 md:right-8 text-white/70 hover:text-white transition-colors p-2"
            aria-label="Close gallery"
          >
            <X className="w-8 h-8 md:w-10 md:h-10" />
          </button>

          <button 
            onClick={showPrev}
            className="absolute left-2 md:left-8 top-1/2 transform -translate-y-1/2 text-white/70 hover:text-secondary transition-colors p-2 hover:bg-white/10 rounded-full"
            aria-label="Previous image"
          >
            <ChevronLeft className="w-8 h-8 md:w-12 md:h-12" />
          </button>

          <div 
            className="relative max-w-[90vw] max-h-[85vh]" 
            onClick={(e) => e.stopPropagation()} // Prevent closing when clicking image
          >
            <img 
              src={images[selectedIndex]} 
              alt={`Gallery Fullscreen ${selectedIndex + 1}`} 
              className="max-w-full max-h-[85vh] object-contain rounded-sm shadow-2xl"
            />
            <div className="absolute -bottom-8 left-0 w-full text-center text-gray-400 text-sm tracking-widest">
              {selectedIndex + 1} / {images.length}
            </div>
          </div>

          <button 
            onClick={showNext}
            className="absolute right-2 md:right-8 top-1/2 transform -translate-y-1/2 text-white/70 hover:text-secondary transition-colors p-2 hover:bg-white/10 rounded-full"
            aria-label="Next image"
          >
            <ChevronRight className="w-8 h-8 md:w-12 md:h-12" />
          </button>
        </div>
      )}
    </section>
  );
};

export default Gallery;