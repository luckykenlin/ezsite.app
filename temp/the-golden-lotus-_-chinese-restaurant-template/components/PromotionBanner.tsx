import React from 'react';
import { X, Sparkles } from 'lucide-react';

interface PromotionBannerProps {
  onClose: () => void;
}

const PromotionBanner: React.FC<PromotionBannerProps> = ({ onClose }) => {
  return (
    <div className="fixed top-0 left-0 w-full h-12 bg-secondary text-foreground z-[60] flex items-center justify-center px-4 shadow-md animate-fade-in-down">
      <div className="flex items-center gap-2 overflow-hidden whitespace-nowrap">
        <Sparkles className="w-4 h-4 hidden md:block flex-shrink-0" />
        <p className="text-xs md:text-sm font-bold tracking-widest uppercase truncate">
          Grand Opening: Free Dim Sum with orders over $50!
        </p>
        <Sparkles className="w-4 h-4 hidden md:block flex-shrink-0" />
      </div>
      <button 
        onClick={onClose}
        className="absolute right-2 md:right-4 p-2 hover:bg-white/20 rounded-full transition-colors"
        aria-label="Close banner"
      >
        <X className="w-4 h-4" />
      </button>
    </div>
  );
};

export default PromotionBanner;